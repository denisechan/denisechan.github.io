<?php
return new LoggedLayout('root.php', 'login', 'home', array(
	//LOGGED OUT
	'api' => new Layout('api.php', null, array('*' => null)),
	
	'login' => new Layout('login/login.php'),
	
	'validate' => new Layout('login/create-account.php', null, array('*' => null)),
	
	//LOGGED IN
	'home' => new LoggedInLayout('home/home.php', null, array(
		'user' => null, 'manager' => null
	)),
	
	'manage' => new LoggedInLayout('home/manage.php', null, array(
		'view' => new LoggedInLayout('home/manage-view.php'),
		'new' => new LoggedInLayout('home/manage-new.php'),
	)),
	
	'lineup' => new LoggedInLayout('home/lineup.php'),
	
	'inspect' => new LoggedInLayout('home/inspect-lineup.php', null, array(
		'*' => null
	)),
	
	'edit-lineup' => new LoggedInLayout('home/manage-lineup.php', null, array(
		'*' => null
	)),
	
	'lineup-progress' => new LoggedInLayout('home/lineup-progress.php', null, array(
		'*' => null
	))
	
));


/*
THIS IS AN OVERVIEW OF LAYOUT PARAMETERS

Layouts have 1 or 3 parameters (if you include the second parameter, you must also include the 3rd)

$layout = new Layout($fileToDisplay, $defaultInnerPageName, $arrayOfInnerPages);

=====FILE-TO-DISPLAY
$fileToDisplay is the php filename of a file within the "layout" directory of the application.

E.x. 
- Your web root is at https://www.domain.com
- Your application site is at https://www.domain.com/app
- The directory with your application files is /var/www/html/app
- This means that your layout file is at /var/www/html/app/layout

Say you want to make a page for logging in at https://www.domain.com/app/login
1) Add a file to handle the login logic in the layout directory:
	|
	|	/var/www/html/app/layout/login.php
	|
	
2) Add an entry to the root Layout object:
	|
	|	'login' => new Layout('login.php')
	|
	
=====DEFAULT-INNER-PAGE-NAME
$defaultInnerPageName tells the application to automatically display one of the
inner pages, even if the user has not explicitly requested it in the URL

Going back to the previous example, say that you want your login page to be the
default page that gets showed, even if the user has visited the root URL of your
site and not explicitly the login one
(They've gone to "https://www.domain.com/app" instead of "https://www.domain.com/app/login")

All you need to do is set the 2nd parameter of the root layout object to be "login".
This will indicate to the application that if no inner page is specifically requested,
the login page should be displayed:

1) Here's what we already had...
	|
	|	return new Layout('root.php', null, array(
	|		'login' => 'new Layout('login.php')
	|	));
	|
	
2) And here's what we want to convert it to. Notice that the only change is the 2nd
parameter of the root Layout object.
	|
	|	return new Layout('root.php', 'login', array(
	|		'login' => 'new Layout('login.php')
	|	));
	|

=====ARRAY-OF-INNER-PAGES
$arrayOfInnerPages holds all the pages that exist within a parent Layout object.
Note the difference between keys in the $arrayOfInnerPages array, and filename
parameters in the Layout object.

When we added the login page, the key in the array identified the URL which can
be used to visit the login page, whereas the 1st parameter to the Layout object
identifies which php file is responsible for generating this page.

Here's what we had originally:
	|
	|	'login' => 'new Layout('login.php')
	|

If we had done this:
	|
	|	'entry' => 'new Layout('login.php')
	|

Then the URL https://www.domain.com/app/login would no longer function, and instead
the user would need to visit https://www.domain.com/app/entry.

If we had done this:
	|
	|	'entry' => 'new Layout('poorly-named-login-file.php')
	|

We would also need to rename 
/var/www/html/app/layout/login.php to /var/www/html/app/layou/poorly-named-login-file.php
*/
