<?php
$app = App::$instance;
$app->addLook('login/create-account-look.css');
$app->addDomFeel('login/create-account-feel.js');
$db = $app->getDb();

//The next part of the URL doesn't identify a page, but is instead an encrypted email
$encEmail = $LAYOUT->getTarget();
$email = decrypt($encEmail);

//Fail silently for improper emails (probably a malicious attempt)
if (!isValidEmail($email)) $app->redirect('login');

//Fail with a message if the email is already taken by another user
if ($db->exists('User', array('Email' => $email))) $app->redirect('login', array('notify' => 'Email already in use.', 'err' => true));

$form = new Form('create-account');
$form->dbName = 'User'; //The create-account form populates the "User" table

$user = $form->addInput(new DBUniqueInput('unique-user', 'User', 'User'));
$user->dbName = 'User';
$user->charLimit = new Range(5, 16);
$user->wordLimit = new Range(1, 1);
$user->process(); //Listen for ajax requests here

$pass1 = $form->addInput(new TextInput('pass'));
$pass1->dbName = 'Password';
$pass1->charLimit = new Range(5, 30);

$pass2 = $form->addInput(new TextInput('pass2'));
$pass2->hasDbRelevance = false;

$email = $form->addInput(new HardInput('email', $email));
$email->dbName = 'Email';

$submit = $form->addInput(new SubmitInput('submit'));

$form->onSubmit = function($formValues, $errs, $form) {
	if ($errs === null) {
		$form->dbPush();
		App::$instance->redirect('login', array('notify' => 'Your account has been created!'));
	} else {
		$form->retry($errs);
	}
};
$form->process();
?>
<div class="page create-account">
	<div class="content">
		<div class="form create-account">
			<h1>Create Account</h1>
			<form <?php $form->wAttrs(); ?>>
				<div class="fields">
					<div id="username-field" class="field">
						<input type="text" <?php $user->wAttrs(); ?> placeholder="Username"/>
					</div>
					<div class="field">
						<input type="password" <?php $pass1->wAttrs(); ?> placeholder="Password!"/>
					</div>
					<div class="field">
						<input type="password" <?php $pass2->wAttrs(); ?> placeholder="Password!!"/>
					</div>
					<div class="field">
						<input type="submit" <?php $submit->wAttrs(); ?>/>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>