<?php

//============================Initiation======================
$home = '/home/'.get_current_user();
$f3 = require($home.'/AboveWebRoot/fatfree-master/lib/base.php');

$f3->set('SESSION.auto_start', true);
$f3->set('AUTOLOAD','autoload/;'.$home.'/AboveWebRoot/autoload/'); // autoload Controller class(es) and anything hidden above web root, e.g. DB stuff

$db = DatabaseConnection::connect(); // defined as autoloaded class in AboveWebRoot/autoload/
$f3->set('DB', $db);

$f3->set('DEBUG',3);		// set maximum debug level
$f3->set('UI','ui/');		// folder for View templates



//==============================Welcome=================================================
$f3->route('GET /',
    function ($f3)
    {
        $f3->set('html_title','Bettles Insight');
//        $f3->set('content','simpleHome.html');
        $f3->set('content','home.html');
        echo template::instance()->render('layout.html');
    }
);

//===============================Preview============================
$f3->route('GET /main',
    function($f3)
    {
        $controller = new SimpleController('beetles');
        $allData = $controller->getData();
        $f3->set("dbData", $allData);
        $f3->set('html_title', 'Bettles Insight');
        $f3->set('content','preview.html');

        echo template::instance()->render('layout.html');
    }
);

//===============================Beetles============================
$f3->route('GET /@beetleName',
    function($f3, $params) {
        // Get beetle name from previous page
        $beetle_name = $params['beetleName'];
        $f3->set('beetleName', $beetle_name);
        $f3->set('html_title',$f3->get('PARAMS.beetleName'));

        // Get beetle information
        $controller = new SimpleController('beetles');
        $beetleInfo = $controller->getAllBeetleData($beetle_name);
        $f3->set('beetleInfo', $beetleInfo);

        // Get comment information
        $controller1 = new SimpleController('forum');
        $comment = $controller1->getCommentData($beetle_name);
        $f3->set('comment', $comment);

        $f3->set('content','/description/'.$beetle_name.'.html');
        echo template::instance()->render('layout.html');
    }
);

$f3->route('POST /addComment',
    function($f3) {
        // Assuming you have a session check function
        if (!$f3->exists('SESSION.user_name')) {
            // If not logged in, store error message and redirect to the login page
            $f3->set('SESSION.error', 'You must be logged in to post comments.');
            $f3->reroute('/login');
        } else {
            // User is logged in, process the comment
            $comment = $f3->get('POST.comment'); // The comment text from the form
            $userName = $f3->get('SESSION.user_name'); // The ID of the logged-in user
            $beetleName = $f3->get('POST.beetleName'); // Assume this is passed from the form

            // Validate and sanitize comment input
            $comment = filter_var($comment, FILTER_SANITIZE_STRING);

            $controller = new SimpleController('forum');
            $result = $controller->putIntoCommentDatabase($comment);
            // Save the comment to the database

            if ($result === false) {
                // Handle error, for example, log it and set an error message
                $f3->set('SESSION.error', 'There was an error posting your comment.');
            } else {
                // Set a success message
                $f3->set('SESSION.success', 'Comment posted successfully.');
            }

            // Redirect back to the beetle page
            $f3->reroute("/beetle/$beetleName"); // Adapt the URL to your needs
        }
    }
);


//=============================Login Form==============================================
$f3->route('GET /login',
    function($f3)
    {
        $f3->set('html_title','Log in');
        $f3->set('content','form.html');
        echo template::instance()->render('layout.html');
    });


$f3->route('POST /signup',
    function($f3) {
        $password = $f3->get('POST.password');
        $confirm_password = $f3->get('POST.confirm_password');
        $formdata = array();

        $controller = new SimpleController('users');
        if ($password === $confirm_password) {
//            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $formdata["username"] = $f3->get('POST.username');
            $formdata["email"] = $f3->get('POST.email');
            $formdata["password"] = $f3->get('POST.password');
            $controller->putIntoUserDatabase($formdata);
            $f3->set('html_title', 'Home');
            $f3->set('content', 'home.html');
            echo template::instance()->render('layout.html');
        }
    });


$f3->route('POST /login',
    function($f3)
    {
        $db = $f3->get('DB');

        $username = $f3->get('POST.username');
        $password = $f3->get('POST.password');
        $controller = new SimpleController('users');
        $userdata = $controller->getAllUserData($username);

        if($userdata && $password === $userdata['password']){
            $f3->reroute('/home');
        }else{
            $f3->set('SESSION.login_error', 'Invalid username or password.');
            $f3->reroute('/login?error=invalid');
        }
});


//==================================Contact============================================
$f3->route('GET /contact',
    function($f3)
    {
        $f3->set('html_title','About us');
        $f3->set('content','contact.html');
        echo template::instance()->render('layout.html');
    }
);


//==================================Resource============================================
$f3->route('GET /resource',
    function($f3)
    {
        $f3->set('html_title','Resource');
        $f3->set('content','resource.html');
        echo template::instance()->render('layout.html');
    }
);

//==============================================================================
function pprint_var($var)
{
    ob_start();
    var_dump($var);
    return ob_get_clean();
}

//$f3->set('ONERROR', // what to do if something goes wrong.
//    function($f3) {
//        $f3->set('html_title',$f3['ERROR']['code']);
//        $f3->set('DUMP', pprint_var($f3['ERROR']));
//        $f3->set('content','error.html');
//        echo template::instance()->render('layout.html');
//    }
//);

//==============================================================================
// Run the FFF engine //
$f3->run();
