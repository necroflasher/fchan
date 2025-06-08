<?PHP

function render_front()
{
	echo '<TITLE>fchan</TITLE>';

	echo '<B><I><A href="',FRONT_PUBLIC,'">fchan</A></I></B>';
	echo '<HR>';

	echo '<FORM action="',FRONT_PUBLIC,'" method="POST" enctype="multipart/form-data">';
	echo '<INPUT type="hidden" name="p" value="up">';
	echo '<TABLE border>';
	echo '<TR>';
	echo   '<TH>Subject';
	echo   '<TD><INPUT type="text" name="subject">';
	echo '<TR>';
	echo   '<TH>Name';
	echo   '<TD><INPUT type="text" name="name">';
	echo '<TR>';
	echo   '<TH>Comment';
	echo   '<TD><TEXTAREA name="body" rows=4 cols=40></TEXTAREA>';
	echo '<TR>';
	echo   '<TH>File';
	echo   '<TD><INPUT type="file" name="file">';
	echo '<TR>';
	echo   '<TH>';
	echo   '<TD><INPUT type="submit">';
	echo '</TABLE>';
	echo '</FORM>';
	echo '<HR>';

	echo '<TABLE border>';
	echo '<TR>';
	echo '<TH>No.';
	echo '<TH>Name';
	echo '<TH>File';
	echo '<TH>Subject';
	echo '<TH>Size';
	echo '<TH>Replies';
	echo '<TH>';
	foreach (db_get_front() as $t)
	{
		$ftext = htmlspecialchars($t['fname']).$t['fext'];
		$ftitle = $ftext;
		if (mb_strlen($t['fname']) > 20)
			$ftext = htmlspecialchars(mb_substr($t['fname'], 0, 20)).'(...)'.$t['fext'];

		echo '<TR>';
		echo '<TD align="right">',$t['tno'];
		echo '<TD><B>',$t['name']?$t['name']:'Anonymous','</B>';
		echo '<TD><A';
		echo ' href="',FILES_DIR_PUBLIC,'/',htmlspecialchars($t['fname']),$t['fext'],'"';
		echo ' title="',$ftitle,'"';
		echo '>',$ftext,'</A>';
		echo '<TD><B>',$t['subject'],'</B>';
		echo '<TD>',format_fs($t['fsize']);
		echo '<TD>',$t['coms'];
		echo '<TD>[ <A href="?v=thread&no=',$t['tno'],'">Reply</A> ]';
	}
	echo '</TABLE>';
}

function render_thread()
{
	$no = userint(@$_GET['no']);

	$dat = db_get_thread($no);

	if (!$dat)
	{
		http_response_code(404);
		die();
	}

	echo '<TITLE>No. ',$no,' @ fchan</TITLE>';

	echo '<B><I><A href="',FRONT_PUBLIC,'">fchan</A></I></B>';
	echo '<HR>';

	if ($dat[0]['subject'])
	{
		echo '<FONT size="+1"><B>',$dat[0]['subject'],'</B> (',$no,')</FONT><BR>';
		echo '<HR>';
	}

	foreach ($dat as $i => $t)
	{
		if ($t['deleted'])
		{
			$t = [
				'tno'  => $t['tno'],
				'cno'  => $t['cno'],
				'name' => 'Anonymous',
				'body' => '<I>Post deleted.</I>',
				'md5'  => null,
			];
		}
		echo $t['cno'],': <B>';
		echo $t['name']?$t['name']:'Anonymous';
		echo '</B>';
		echo ' [';
		echo '<A href="?v=options&no=',$t['tno'],'&com=',$t['cno'],'">';
		echo 'Options';
		echo '</A>';
		echo ']';
		echo '<BLOCKQUOTE>';
		if (!(!$i && !$t['body']))
		{
			echo $t['body']?$t['body']:'<I>No comment.</I>';
		}
		if (!$i && $t['md5'])
		{
			if ($t['body'])
				echo '<BR><BR>';
			echo '<A href="',FILES_DIR_PUBLIC,'/',htmlspecialchars($t['fname']),$t['fext'],'">';
			echo htmlspecialchars($t['fname']),$t['fext'];
			echo '</A>';
			
		}
		echo '</BLOCKQUOTE>';
		echo '<HR>';
	}

	echo '<FORM action="',FRONT_PUBLIC,'" method="POST">';
	echo '<INPUT type="hidden" name="p" value="re">';
	echo '<INPUT type="hidden" name="no" value="',$no,'">';
	echo '<TABLE border>';
	echo '<TR>';
	echo   '<TH>Name';
	echo   '<TD><INPUT type="text" name="name">';
	echo '<TR>';
	echo   '<TH>Comment';
	echo   '<TD><TEXTAREA name="body" rows=4 cols=40></TEXTAREA>';
	echo '<TR>';
	echo   '<TH>';
	echo   '<TD><INPUT type="submit">';
	echo '</TABLE>';
	echo '</FORM>';
}

function render_options()
{
	$tno = userint(@$_GET['no']);
	$cno = userint(@$_GET['com']);

	$t = db_get_comment($tno, $cno);

	$t or die('Error: Post not found.');

	if ($t['md5']) $t['md5'] = bin2hex($t['md5']);
	$t['time'] = date('Y-m-d H:i:s', $t['time']);

	echo '<TITLE>Post details @ fchan</TITLE>';

	echo '<B><I><A href="',FRONT_PUBLIC,'">fchan</A></I></B>';
	echo '<HR>';

	$ks = 'tno.cno.time.subject.name.body.fname.fext.fsize.md5.deleted.fpurged';
	echo '<TABLE border>';
	foreach (explode('.', $ks) as $k)
	{
		if (!$t[$k])
			continue;
		echo '<TR>';
		echo '<TH>',$k;
		echo '<TD>',htmlspecialchars($t[$k]);
	}
	echo '</TABLE>';

	echo '<P>';

	echo '<FORM action="',FRONT_PUBLIC,'" method="POST">';
	echo '<INPUT type="hidden" name="p" value="del">';
	echo '<INPUT type="hidden" name="tno" value="',$tno,'">';
	echo '<INPUT type="hidden" name="cno" value="',$cno,'">';
	echo '<FIELDSET>';
	echo '<LEGEND>Delete post</LEGEND>';
	echo '<INPUT type="submit">';
	echo '</FIELDSET>';
	echo '</FORM>';
}
