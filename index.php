<?PHP

# ░░░████▌█████▌█░████████▐▀██▀
# ░▄█████░█████▌░█░▀██████▌█▄▄▀▄
# ░▌███▌█░▐███▌▌░░▄▄░▌█▌███▐███░▀
# ▐░▐██░░▄▄▐▀█░░░▐▄█▀▌█▐███▐█
# ░░███░▌▄█▌░░▀░░▀██░░▀██████▌
# ░░░▀█▌▀██▀░▄░░░░░░░░░███▐███
# ░░░░██▌░░░░░░░░░░░░░▐███████▌
# ░░░░███░░░░░▀█▀░░░░░▐██▐███▀▌
# ░░░░▌█▌█▄░░░░░░░░░▄▄████▀░▀
# ░░░░░░█▀██▄▄▄░▄▄▀▀▒█▀█░

# ni:
# - more deletion stuff
#   - cooldown timer
# - spam protection
#   - first, prevent accidental resubmission
#   - post cooldown (show using post confirm screen?)
# eh who cares:
# - sage

const FCHAN_DB = '/tmp/fchan.30.db';

const FRONT_PUBLIC = '/fchan/';

const FILES_DIR        = '/home/user/127.1.1.1/fchan/up';
const FILES_DIR_PUBLIC = '/fchan/up';

const EXTS = [
	'.png'  => 'image/png',
	'.jpeg' => 'image/jpeg',
	'.jpg'  => 'image/jpeg',
	'.gif'  => 'image/gif',
	'.swf'  => 'application/x-shockwave-flash',
];

define('SHORTTAGS', '?HPJAGLO');
define('LONGTAGS', explode(' ',
	'Unknown Hentai Porn Japanese Anime Game Loop Other'));

require_once 'db.php';
require_once 'lib.php';
require_once 'process.php';
require_once 'render.php';

if (!file_exists(FCHAN_DB))
{
	db_firstrun();
}
if (!is_dir(FILES_DIR))
{
	mkdir(FILES_DIR) or die;
}

switch (@strval($_POST['p']))
{
	case 'up':  process_up();  exit();
	case 're':  process_re();  exit();
	case 'del': process_del(); exit();
}

switch (@strval($_GET['v']))
{
	case '':
	case 'index':   render_front();   exit();
	case 'options': render_options(); exit();
	case 'thread':  render_thread();  exit();
}

http_response_code(404);
exit();
