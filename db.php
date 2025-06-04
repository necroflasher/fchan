<?PHP

function db_firstrun()
{
	$db = new SQLite3(FCHAN_DB, SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
	$db->enableExceptions(true);
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
		deleted INTEGER
	) STRICT
	");
	$db->close();
}

function db_open()
{
	$db = new SQLite3(FCHAN_DB, SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
	$db->exec('PRAGMA journal_mode=WAL');
	return $db;
}

function db_can_up($t)
{
	$rv = true;
	$db = db_open();
	$q = $db->prepare('
	SELECT 1 FROM dat WHERE md5=? OR (fname=? AND fext=?)
	');
	$q->bindValue(1, $t['md5'],   SQLITE3_BLOB);
	$q->bindValue(2, $t['fname'], SQLITE3_TEXT);
	$q->bindValue(3, $t['fext'],  SQLITE3_TEXT);
	$res = $q->execute();
	if ($res && $res->numColumns() && $res->fetchArray(SQLITE3_NUM))
		$rv = false;
	$q->close();
	$db->close();
	return $rv;
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
	$q->bindValue(1, $t['sub'],   SQLITE3_TEXT);
	$q->bindValue(2, $t['nam'],   SQLITE3_TEXT);
	$q->bindValue(3, $t['com'],   SQLITE3_TEXT);
	$q->bindValue(4, $t['md5'],   SQLITE3_BLOB);
	$q->bindValue(5, $t['fname'], SQLITE3_TEXT);
	$q->bindValue(6, $t['fext'],  SQLITE3_TEXT);
	$q->bindValue(7, $t['fsize'], SQLITE3_INTEGER);
	$res = $q->execute();
	$q->close();
	$db->close();
	if (!$res)
		return 'Failed to insert post.';
	return '';
}

function db_re($t)
{
	$db = db_open();
	$q = $db->prepare('
	INSERT INTO dat (
		cno,
		tno, name, body)
	VALUES (
		(SELECT 1+COUNT(1) FROM dat WHERE tno=?),
		?, ?, ?)
	');
	$q->bindValue(1, $t['tno'],  SQLITE3_INTEGER);
	$q->bindValue(2, $t['tno'],  SQLITE3_INTEGER);
	$q->bindValue(3, $t['name'], SQLITE3_TEXT);
	$q->bindValue(4, $t['body'], SQLITE3_TEXT);
	$res = $q->execute();
	$q->close();
	$db->close();
	if (!$res)
		return 'Failed to insert post.';
	return '';
}

function db_get_front()
{
	$rv = [];
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
		WHERE md5 NOT NULL
		ORDER BY rowid DESC
		LIMIT 10
	) ORDER BY maxt DESC
	');
	$res = $q->execute();
	if ($res->numColumns())
		while ($row = $res->fetchArray(SQLITE3_ASSOC))
			$rv[] = $row;
	$q->close();
	$db->close();
	return $rv;
}

function db_get_thread($no)
{
	$rv = [];
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
	$q->bindValue(1, $no, SQLITE3_INTEGER);
	$res = $q->execute();
	if ($res->numColumns())
		while ($row = $res->fetchArray(SQLITE3_ASSOC))
			$rv[] = $row;
	$q->close();
	$db->close();
	return $rv;
}
