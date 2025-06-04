<?PHP

# now's the critical period when you're 2 steps from overengineering it

# ni:
# - sage
# - thread pururin
# - deletion
#   - blank out comments
#   - threads gone from index
# - file tags
# - files stored with original filenames
#   - sanitize
# - quote links
# - spam protection
#   - first, prevent accidental resubmission
#   - post cooldown (show using post confirm screen?)

const FCHAN_DB = '/tmp/fchan.20.db';

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

require_once 'db.php';
require_once 'lib.php';
require_once 'process.php';
require_once 'render.php';

if (!file_exists(FCHAN_DB))
{
	db_firstrun();
}

switch (strval(@$_POST['p']))
{
	case 'up': process_up(); exit();
	case 're': process_re(); exit();
}

switch (strval(@$_GET['v']))
{
	case '':
	case 'index':  render_front();  exit();
	case 'thread': render_thread(); exit();
}

http_response_code(404);
exit();
