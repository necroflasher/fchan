<?PHP

function db_open()
{
	$db = new PDO('sqlite:'.FCHAN_DB);
	$db->exec('PRAGMA busy_timeout=5000');
	$db->exec('PRAGMA journal_mode=WAL');
	$db->exec('PRAGMA synchronous=NORMAL');
	$db->exec('PRAGMA temp_store=MEMORY');
	return $db;
}

function db_firstrun()
{
	$db = db_open();
	$db->exec("
	CREATE TABLE dat (
		tno     INTEGER NOT NULL CHECK(tno>=1),
		cno     INTEGER NOT NULL CHECK(cno>=1),
		time    INTEGER NOT NULL,
		pass    TEXT    NOT NULL CHECK(pass<>''),
		subject TEXT,
		name    TEXT,
		body    TEXT,
		md5     BLOB,
		fname   TEXT,
		fext    TEXT,
		fsize   INTEGER,
		deleted INTEGER,
		fpurged INTEGER
	) STRICT
	");
	$db->exec('
	CREATE UNIQUE INDEX dat_tno_cno ON dat(tno, cno)
	');
	$db->exec('
	CREATE UNIQUE INDEX dat_tno ON dat(tno) WHERE cno=1
	');
	$db->exec('
	CREATE INDEX dat_alive ON dat(tno) WHERE cno=1 AND fpurged IS NULL
	');
}

function db_up($t, &$tno_out, &$fpurge_dat_out)
{
	$db = db_open();

	$pass = $t['pass'];
	if ($pass === '')
		$pass = bin2hex(random_bytes(16));
	$hash = password_hash($pass, PASSWORD_BCRYPT);

	$db->beginTransaction();

	$q = $db->prepare('
	SELECT tno FROM dat WHERE
		cno=1 AND
		fpurged IS NULL AND
		(md5=? OR (fname=? AND fext=?))
	');
	$q->bindValue(1, $t['md5'],   PDO::PARAM_LOB);
	$q->bindValue(2, $t['fname'], PDO::PARAM_STR);
	$q->bindValue(3, $t['fext'],  PDO::PARAM_STR);
	$q->execute();
	if ($tno_out = $q->fetchColumn())
		return 'File exists.';

	$q = $db->prepare('
	SELECT MAX(tno) FROM dat WHERE cno=1
	');
	$q->execute();
	$lastup = $q->fetchColumn();

	$q = $db->prepare('
	INSERT INTO dat (tno, cno, time, pass,
		subject, name, body, md5, fname, fext, fsize)
	VALUES (?, 1, UNIXEPOCH(), ?,
		?, ?, ?, ?, ?, ?, ?)
	');
	$q->bindValue(1, $lastup+1,     PDO::PARAM_STR);
	$q->bindValue(2, $hash,         PDO::PARAM_STR);
	$q->bindValue(3, $t['subject'], PDO::PARAM_STR);
	$q->bindValue(4, $t['name'],    PDO::PARAM_STR);
	$q->bindValue(5, $t['body'],    PDO::PARAM_STR);
	$q->bindValue(6, $t['md5'],     PDO::PARAM_LOB);
	$q->bindValue(7, $t['fname'],   PDO::PARAM_STR);
	$q->bindValue(8, $t['fext'],    PDO::PARAM_STR);
	$q->bindValue(9, $t['fsize'],   PDO::PARAM_INT);
	$q->execute();

	$q = $db->prepare('
	SELECT fname, fext FROM dat
	WHERE cno=1 AND fpurged IS NULL
	ORDER BY tno DESC
	LIMIT -1 OFFSET 10
	');
	$q->execute();
	$fpurge_dat = $q->fetchAll();

	$q = $db->prepare('
	UPDATE dat
	SET fpurged=1
	WHERE cno=1 AND tno IN (
		SELECT tno FROM dat
		WHERE cno=1 AND fpurged IS NULL
		ORDER BY tno DESC
		LIMIT -1 OFFSET 10)
	');
	$q->execute();

	$db->commit();

	$tno_out = $lastup+1;
	$fpurge_dat_out = $fpurge_dat;

	return '';
}

function db_re($t, &$cno_out)
{
	$db = db_open();

	$pass = $t['pass'];
	if ($pass === '')
		$pass = bin2hex(random_bytes(16));
	$hash = password_hash($pass, PASSWORD_BCRYPT);

	$db->beginTransaction();

	$q = $db->prepare('
	SELECT deleted, fpurged FROM dat
	WHERE tno=? AND cno=1
	');
	$q->bindValue(1, $t['tno'], PDO::PARAM_INT);
	$q->execute();
	$dat = $q->fetch();
	if (!$dat)
		return 'Thread does not exist.';
	if ($dat['deleted'] || $dat['fpurged'])
		return 'Thread is expired or deleted.';

	$q = $db->prepare('SELECT MAX(cno) FROM dat WHERE tno=?');
	$q->bindValue(1, $t['tno'], PDO::PARAM_INT);
	$q->execute();
	$lastcom = $q->fetchColumn();
	if ($lastcom >= 1000)
		return 'Reply limit reached.';

	$q = $db->prepare('
	INSERT INTO dat (tno, cno, time, pass, name, body)
	VALUES (?, ?, UNIXEPOCH(), ?, ?, ?)
	');
	$q->bindValue(1, $t['tno'],  PDO::PARAM_INT);
	$q->bindValue(2, $lastcom+1, PDO::PARAM_INT);
	$q->bindValue(3, $hash,      PDO::PARAM_STR);
	$q->bindValue(4, $t['name'], PDO::PARAM_STR);
	$q->bindValue(5, $t['body'], PDO::PARAM_STR);
	$q->execute();

	$db->commit();

	$cno_out = $lastcom+1;

	return '';
}

function db_del($tno, $cno, $pass, &$dat_out)
{
	$db = db_open();

	$db->beginTransaction();

	$q = $db->prepare("
	SELECT * FROM dat WHERE tno=? AND cno=?
	");
	$q->bindValue(1, $tno, PDO::PARAM_INT);
	$q->bindValue(2, $cno, PDO::PARAM_INT);
	$q->execute();
	$dat = $q->fetch();
	if (!$dat)
		return 'Post not found.';
	if ($dat['deleted'])
		return 'Post has already been deleted.';
	if (!password_verify($pass, $dat['pass']))
		return 'Wrong password.';
	$dat_out = $dat;

	$q = $db->prepare("
	UPDATE dat
	SET
		deleted=1,
		subject=NULL,
		name=NULL,
		body=NULL,
		fname=NULL,
		fpurged=(CASE cno WHEN 1 THEN 1 ELSE NULL END)
	WHERE tno=? AND cno=?
	");
	$q->bindValue(1, $tno, PDO::PARAM_INT);
	$q->bindValue(2, $cno, PDO::PARAM_INT);
	$q->execute();

	$db->commit();

	return '';
}

function db_get_front()
{
	$db = db_open();
	$q = $db->prepare('
	SELECT
		*,
		(SELECT MAX(cno)-1 FROM dat AS d WHERE d.tno=dat.tno) AS coms,
		(SELECT MAX(rowid) FROM dat AS d WHERE d.tno=dat.tno) AS _bump
	FROM dat
	WHERE cno=1 AND fpurged IS NULL
	ORDER BY _bump DESC
	LIMIT 10
	');
	$q->execute();
	return $q->fetchAll();
}

function db_get_thread($tno)
{
	$db = db_open();
	$q = $db->prepare('SELECT * FROM dat WHERE tno=?');
	$q->bindValue(1, $tno, PDO::PARAM_INT);
	$q->execute();
	return $q->fetchAll();
}

function db_get_comment($tno, $cno)
{
	$db = db_open();
	$q = $db->prepare('SELECT * FROM dat WHERE tno=? AND cno=?');
	$q->bindValue(1, $tno, PDO::PARAM_INT);
	$q->bindValue(2, $cno, PDO::PARAM_INT);
	$q->execute();
	return $q->fetch();
}
