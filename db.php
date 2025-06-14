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
		tno      INTEGER NOT NULL CHECK(tno>=1),
		cno      INTEGER NOT NULL CHECK(cno>=1),
		ip       TEXT             CHECK(ip<>''),
		pass     TEXT    NOT NULL CHECK(pass<>''),
		tcreated REAL    NOT NULL,
		tdeleted REAL,
		tfpurged REAL,
		subject  TEXT,
		name     TEXT,
		body     TEXT,
		md5      BLOB,
		fname    TEXT,
		fext     TEXT,
		fsize    INTEGER,
		ftag     INTEGER
	) STRICT
	");
	$db->exec('
	CREATE UNIQUE INDEX dat_tno_cno ON dat(tno, cno)
	');
	$db->exec('
	CREATE UNIQUE INDEX dat_tno ON dat(tno) WHERE cno=1
	');
	$db->exec('
	CREATE INDEX dat_alive ON dat(tno) WHERE cno=1 AND tfpurged IS NULL
	');
	$db->exec('
	CREATE INDEX dat_reposts ON dat(md5, tcreated) WHERE md5 NOT NULL
	');
	$db->exec('
	CREATE INDEX dat_iplog ON dat(ip, tcreated) WHERE ip NOT NULL
	');
	$db->exec("
	CREATE TABLE ipban (
		ip     TEXT PRIMARY KEY NOT NULL,
		expiry REAL,
		reason TEXT
	) STRICT
	");
}

function dbc_checkban($db)
{
	$q = $db->prepare("
	SELECT
		(expiry IS NULL OR expiry > UNIXEPOCH('subsec')) AS banned,
		ROUND(expiry - UNIXEPOCH('subsec')) AS remainder,
		reason
	FROM ipban WHERE ip=?
	");
	$q->bindValue(1, userip(), PDO::PARAM_STR);
	$q->execute();
	$b = $q->fetch();
	if (!$b || !$b['banned'])
		return '';
	if ($b['remainder'])
		$msg = "This IP address is banned for {$b['remainder']} more seconds.";
	else
		$msg = "This IP address is permanently banned.";
	if ($b['reason'])
		$msg .= ' Reason: '.$b['reason'];
	return $msg;
}

function db_up($t, &$tno_out, &$fpurge_dat_out)
{
	$db = db_open();

	$pass = $t['pass'];
	if ($pass === '')
		$pass = bin2hex(random_bytes(16));
	$hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 8]);

	$db->beginTransaction();

	# [1/9] check bans

	$err = dbc_checkban($db);
	if ($err)
		return $err;

	# [2/9] check post cooldown

	$q = $db->prepare("
	SELECT UNIXEPOCH('subsec') - MAX(tcreated) FROM dat WHERE ip=?
	");
	$q->bindValue(1, userip(), PDO::PARAM_STR);
	$q->execute();
	if (($val = $q->fetchColumn()) !== null && $val < 2*60)
		return 'Please wait a while before making a thread again.';

	# [3/9] check number of active threads
	#       - relax this and only consider recent threads

	$q = $db->prepare("
	SELECT COUNT(1) >= 6 FROM dat WHERE
		cno=1 AND tfpurged IS NULL AND
		ip=? AND (UNIXEPOCH('subsec') - tcreated) < 3*24*60*60
	");
	$q->bindValue(1, userip(), PDO::PARAM_STR);
	$q->execute();
	if ($q->fetchColumn())
		return 'Please wait a while before making a thread again.';

	# [4/9] check duplicate file

	$q = $db->prepare('
	SELECT tno FROM dat WHERE
		cno=1 AND
		tfpurged IS NULL AND
		(md5=? OR (fname=? AND fext=?))
	');
	$q->bindValue(1, $t['md5'],   PDO::PARAM_LOB);
	$q->bindValue(2, $t['fname'], PDO::PARAM_STR);
	$q->bindValue(3, $t['fext'],  PDO::PARAM_STR);
	$q->execute();
	if ($tno_out = $q->fetchColumn())
		return 'File exists.';

	# [5/9] check file cooldown

	$q = $db->prepare("
	SELECT (UNIXEPOCH('subsec') - tcreated) < 24*60*60 FROM dat
	WHERE md5=?
	ORDER BY tcreated DESC
	");
	$q->bindValue(1, $t['md5'], PDO::PARAM_LOB);
	$q->execute();
	if ($q->fetchColumn())
		return 'Please wait a while before posting this file again.';

	# [6/9] get current thread number

	$q = $db->prepare('
	SELECT MAX(tno) FROM dat WHERE cno=1
	');
	$q->execute();
	$lastup = $q->fetchColumn();

	# [7/9] insert thread

	$q = $db->prepare("
	INSERT INTO dat (tno, cno, tcreated, pass, ip,
		subject, name, body, md5, fname, fext, fsize, ftag)
	VALUES (?, 1, UNIXEPOCH('subsec'), ?, ?,
		?, ?, ?, ?, ?, ?, ?, ?)
	");
	$q->bindValue(1,  $lastup+1,     PDO::PARAM_STR);
	$q->bindValue(2,  $hash,         PDO::PARAM_STR);
	$q->bindValue(3,  userip(),      PDO::PARAM_STR);
	$q->bindValue(4,  $t['subject'], PDO::PARAM_STR);
	$q->bindValue(5,  $t['name'],    PDO::PARAM_STR);
	$q->bindValue(6,  $t['body'],    PDO::PARAM_STR);
	$q->bindValue(7,  $t['md5'],     PDO::PARAM_LOB);
	$q->bindValue(8,  $t['fname'],   PDO::PARAM_STR);
	$q->bindValue(9,  $t['fext'],    PDO::PARAM_STR);
	$q->bindValue(10, $t['fsize'],   PDO::PARAM_INT);
	$q->bindValue(11, $t['ftag'],    PDO::PARAM_INT);
	$q->execute();

	# [8/9] get threads whose files to purge

	$q = $db->prepare('
	SELECT fname, fext FROM dat
	WHERE cno=1 AND tfpurged IS NULL
	ORDER BY tno DESC
	LIMIT -1 OFFSET 30
	');
	$q->execute();
	$fpurge_dat = $q->fetchAll();

	# [9/9] mark those threads as purged

	$q = $db->prepare("
	UPDATE dat
	SET tfpurged=UNIXEPOCH('subsec')
	WHERE cno=1 AND tno IN (
		SELECT tno FROM dat
		WHERE cno=1 AND tfpurged IS NULL
		ORDER BY tno DESC
		LIMIT -1 OFFSET 30)
	");
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
	$hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 8]);

	$db->beginTransaction();

	# [1/5] check bans

	$err = dbc_checkban($db);
	if ($err)
		return $err;

	# [2/5] check post cooldown

	$q = $db->prepare("
	SELECT UNIXEPOCH('subsec') - MAX(tcreated) FROM dat WHERE ip=?
	");
	$q->bindValue(1, userip(), PDO::PARAM_STR);
	$q->execute();
	if (($val = $q->fetchColumn()) !== null && $val < 60)
		return 'Please wait a while before making a comment again.';

	# [3/5] check flags of thread

	$q = $db->prepare('
	SELECT tdeleted, tfpurged FROM dat
	WHERE tno=? AND cno=1
	');
	$q->bindValue(1, $t['tno'], PDO::PARAM_INT);
	$q->execute();
	$dat = $q->fetch();
	if (!$dat)
		return 'Thread does not exist.';
	if ($dat['tdeleted'] || $dat['tfpurged'])
		return 'Thread is expired or deleted.';

	# [4/5] check reply limit

	$q = $db->prepare('SELECT MAX(cno) FROM dat WHERE tno=?');
	$q->bindValue(1, $t['tno'], PDO::PARAM_INT);
	$q->execute();
	$lastcom = $q->fetchColumn();
	if ($lastcom >= 1000)
		return 'Reply limit reached.';

	# [5/5] insert post

	$q = $db->prepare("
	INSERT INTO dat (tno, cno, tcreated, pass, ip, name, body)
	VALUES (?, ?, UNIXEPOCH('subsec'), ?, ?, ?, ?)
	");
	$q->bindValue(1, $t['tno'],  PDO::PARAM_INT);
	$q->bindValue(2, $lastcom+1, PDO::PARAM_INT);
	$q->bindValue(3, $hash,      PDO::PARAM_STR);
	$q->bindValue(4, userip(),   PDO::PARAM_STR);
	$q->bindValue(5, $t['name'], PDO::PARAM_STR);
	$q->bindValue(6, $t['body'], PDO::PARAM_STR);
	$q->execute();

	$db->commit();

	$cno_out = $lastcom+1;

	return '';
}

function db_del($tno, $cno, $pass, &$dat_out)
{
	$db = db_open();

	$db->beginTransaction();

	# [1/3] check bans

	$err = dbc_checkban($db);
	if ($err)
		return $err;

	# [2/3] check post exists, password matches

	$q = $db->prepare("
	SELECT * FROM dat WHERE tno=? AND cno=?
	");
	$q->bindValue(1, $tno, PDO::PARAM_INT);
	$q->bindValue(2, $cno, PDO::PARAM_INT);
	$q->execute();
	$dat = $q->fetch();
	if (!$dat)
		return 'Post not found.';
	if ($dat['tdeleted'])
		return 'Post has already been deleted.';
	if (!(isadmin() && $pass === '!admindel') &&
	    !password_verify($pass, $dat['pass']))
		return 'Wrong password.';
	$dat_out = $dat;

	# [3/3] set deleted and clear some fields

	$q = $db->prepare("
	UPDATE dat
	SET
		ip=NULL,
		tdeleted=UNIXEPOCH('subsec'),
		tfpurged=(CASE cno WHEN 1 THEN UNIXEPOCH('subsec') ELSE NULL END),
		subject=(CASE cno WHEN 1 THEN '' ELSE NULL END),
		name='',
		body='',
		fname=NULL
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
		(SELECT MAX(cno)-1    FROM dat AS dd WHERE dd.tno=d.tno) AS coms,
		(SELECT MAX(tcreated) FROM dat AS dd WHERE dd.tno=d.tno) AS _maxt,
		(SELECT MAX(rowid)    FROM dat AS dd WHERE dd.tno=d.tno) AS _maxr,
		-- threads newer than this one in dat_alive
		(CASE cno WHEN 1 THEN (
			SELECT COUNT(1) FROM dat AS dd
			WHERE cno=1 AND tfpurged IS NULL AND dd.tno>d.tno)
		ELSE 0 END) AS numnewer
	FROM dat AS d
	WHERE cno=1 AND tfpurged IS NULL
	-- sort by timestamp first (hack for messily imported databases
	-- with non-chronological rowids)
	ORDER BY _maxt DESC, _maxr DESC
	LIMIT 30
	');
	$q->execute();
	return $q->fetchAll();
}

function db_get_thread($tno)
{
	$db = db_open();
	$q = $db->prepare('
	SELECT
		*,
		-- threads newer than this one in dat_alive
		(CASE cno WHEN 1 THEN (
			SELECT COUNT(1) FROM dat AS dd
			WHERE cno=1 AND tfpurged IS NULL AND dd.tno>d.tno)
		ELSE 0 END) AS numnewer
	FROM dat AS d WHERE tno=?
	');
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
