<?PHP

function render_front()
{
	echo '<HTML>';
	echo '<HEAD>';
	echo '<TITLE>fchan</TITLE>';
	echo '</HEAD>';
	echo '<BODY>';

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
	echo   '<TD><TEXTAREA name="body"></TEXTAREA>';
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
		echo '<TR>';
		echo '<TD align="right">',$t['no'];
		echo '<TD><B>',$t['name']?$t['name']:'Anonymous','</B>';
		echo '<TD><A href="',FILES_DIR_PUBLIC,'/',bin2hex($t['md5']),$t['fext'],'">',$t['fname'],$t['fext'],'</A>';
		echo '<TD>',$t['subject'];
		echo '<TD>',format_fs($t['fsize']);
		echo '<TD>',$t['coms'];
		echo '<TD>[ <A href="?v=thread&no=',$t['no'],'">Reply</A> ]';
	}
	echo '</TABLE>';

	echo '</BODY>';
	echo '</HTML>';
}

function render_thread()
{
	$no = intval(strval(@$_GET['no']));

	$dat = db_get_thread($no);

	if (!$dat)
	{
		http_response_code(404);
		die();
	}

	echo '<HTML>';
	echo '<HEAD>';
	echo '<TITLE>No. ',$no,' @ fchan</TITLE>';
	echo '</HEAD>';
	echo '<BODY>';

	echo '<B><I><A href="',FRONT_PUBLIC,'">fchan</A></I></B>';
	echo '<HR>';

	if ($dat[0]['subject'])
	{
		echo '<FONT size="+1"><B>',$dat[0]['subject'],'</B></FONT><BR>';
		echo '<HR>';
	}

	foreach ($dat as $i => $t)
	{
		if ($t['deleted'])
		{
			$t = [
				'cno'  => $t['cno'],
				'name' => 'Anonymous',
				'body' => '<I>Post deleted.</I>',
			];
		}
		echo $t['cno'],': <B>';
		echo $t['name']?$t['name']:'Anonymous';
		echo '</B>';
		echo '<BLOCKQUOTE>';
		if (!(!$i && !$t['body']))
		{
			echo $t['body']?$t['body']:'<I>No comment.</I>';
		}
		if (!$i)
		{
			if ($t['body'])
				echo '<BR><BR>';
			echo '<A href="',FILES_DIR_PUBLIC,'/',bin2hex($t['md5']),$t['fext'],'">';
			echo $t['fname'],$t['fext'];
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
	echo   '<TD><TEXTAREA name="body"></TEXTAREA>';
	echo '<TR>';
	echo   '<TH>';
	echo   '<TD><INPUT type="submit">';
	echo '</TABLE>';
	echo '</FORM>';

	echo '</BODY>';
	echo '</HTML>';
}
