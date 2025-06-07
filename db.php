<?PHP

function db_firstrun()
{
	$db = new PDO('sqlite:'.FCHAN_DB);
	$db->exec('PRAGMA journal_mode=WAL');
	$db->exec("
	CREATE TABLE dat (
		tno     INTEGER,
		cno     INTEGER,
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
	$db = null;
}

function db_open()
{
	$db = new PDO('sqlite:'.FCHAN_DB);
	$db->exec('PRAGMA journal_mode=WAL');
	return $db;
}

function db_can_up($t)
{
	$rv = true;
	$db = db_open();
	$q = $db->prepare('
	SELECT 1 FROM dat WHERE
		deleted IS NULL AND (md5=? OR (fname=? AND fext=?))
	');
	$q->bindValue(1, $t['md5'],   PDO::PARAM_LOB);
	$q->bindValue(2, $t['fname'], PDO::PARAM_STR);
	$q->bindValue(3, $t['fext'],  PDO::PARAM_STR);
	$q->execute();
	if ($q->fetchColumn())
		$rv = false;
	$q = null;
	$db = null;
	return $rv;
}

function db_can_re($tno)
{
	$dat = db_get_front();
	if (!is_array($dat))
		return 'Database error.';
	foreach ($dat as $t)
		if ($t['no'] == $tno)
			return '';
	return 'The thread is expired or deleted.';
}

function db_up($t)
{
	if (!db_can_up($t))
		return 'File exists.';
	$db = db_open();
	$q = $db->prepare('
	INSERT INTO dat (
		tno,
		cno,
		subject, name, body, md5, fname, fext, fsize)
	VALUES (
		(SELECT 1+COUNT(1) FROM dat WHERE cno=1),
		1,
		?, ?, ?, ?, ?, ?, ?)
	');
	$q->bindValue(1, $t['sub'],   PDO::PARAM_STR);
	$q->bindValue(2, $t['nam'],   PDO::PARAM_STR);
	$q->bindValue(3, $t['com'],   PDO::PARAM_STR);
	$q->bindValue(4, $t['md5'],   PDO::PARAM_LOB);
	$q->bindValue(5, $t['fname'], PDO::PARAM_STR);
	$q->bindValue(6, $t['fext'],  PDO::PARAM_STR);
	$q->bindValue(7, $t['fsize'], PDO::PARAM_INT);
	$res = $q->execute();
	$q = null;
	$db = null;
	if (!$res)
		return 'Failed to insert post.';
	return '';
}

function db_re($t)
{
	if ($err = db_can_re($t['tno']))
		return $err;
	$db = db_open();
	$q = $db->prepare('
	INSERT INTO dat (
		cno,
		tno, name, body)
	VALUES (
		(SELECT 1+COUNT(1) FROM dat WHERE tno=?),
		?, ?, ?)
	');
	$q->bindValue(1, $t['tno'],  PDO::PARAM_INT);
	$q->bindValue(2, $t['tno'],  PDO::PARAM_INT);
	$q->bindValue(3, $t['name'], PDO::PARAM_STR);
	$q->bindValue(4, $t['body'], PDO::PARAM_STR);
	$res = $q->execute();
	$q = null;
	$db = null;
	if (!$res)
		return 'Failed to insert post.';
	return '';
}

function db_del($tno, $cno)
{
	# blank out any fields with user-submitted text
	$db = db_open();
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
	$count = $q->rowCount();
	$q = null;
	$db = null;
	if (!$count)
		return 'Post not found.';
	return '';
}

function db_get_front()
{
	$db = db_open();
	# the shitty nested thing is to apply the bump order sort after
	# rowid sort plus limit
	$q = $db->prepare('
	SELECT * FROM (
		SELECT
			tno AS no,
			subject,
			name,
			fname,
			fext,
			fsize,
			md5,
			(SELECT -1+COUNT(1) FROM dat AS d WHERE d.tno=dat.tno) AS coms,
			(SELECT MAX(rowid) FROM dat AS d WHERE d.tno=dat.tno) AS maxt
		FROM dat
		WHERE md5 NOT NULL AND deleted IS NULL
		ORDER BY rowid DESC
		LIMIT 10
	) ORDER BY maxt DESC
	');
	$q->execute();
	$rv = $q->fetchAll();
	$q = null;
	$db = null;
	return $rv;
}

# call after inserting a new thread
# returns threads whose file must be deleted
function db_claim_purge_files()
{
	$dat = db_get_front();
	if (!is_array($dat))
		return [];
	$old = INF;
	foreach ($dat as $t)
		if ($t['no'] < $old)
			$old = $t['no'];
	if (is_infinite($old))
		return [];

	$db = db_open();

	# list
	$q = $db->prepare('
	SELECT * FROM dat
	WHERE tno<? AND cno=1 AND fpurged IS NULL
	');
	$q->bindValue(1, $old, PDO::PARAM_INT);
	$q->execute();
	$rv = $q->fetchAll();

	# mark
	$q = $db->prepare('
	UPDATE dat SET fpurged=1
	WHERE tno<? AND cno=1 AND fpurged IS NULL
	');
	$q->bindValue(1, $old, PDO::PARAM_INT);
	$q->execute();

	$q = null;
	$db = null;
	return $rv;
}

function db_get_thread($no)
{
	$db = db_open();
	$q = $db->prepare('
	SELECT
		tno,
		cno,
		subject,
		name,
		body,
		fname,
		fext,
		deleted,
		md5
	FROM dat
	WHERE tno=?
	');
	$q->bindValue(1, $no, PDO::PARAM_INT);
	$q->execute();
	$rv = $q->fetchAll();
	$q = null;
	$db = null;
	return $rv;
}

function db_get_comment($tno, $cno)
{
	$db = db_open();
	$q = $db->prepare('SELECT * FROM dat WHERE tno=? AND cno=?');
	$q->bindValue(1, $tno, PDO::PARAM_INT);
	$q->bindValue(2, $cno, PDO::PARAM_INT);
	$q->execute();
	$rv = $q->fetch();
	$q = null;
	$db = null;
	return $rv;
}
