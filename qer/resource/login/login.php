<?php
$app = App::$instance;
$app->addLook('login/login-look.css');
$app->addFeel('login/login-feel.js');

//Keep the state of whether the user wants to be filling out the login or signin form
$showLoginForm = new BooleanAjaxValue('show-login-form');
$showLoginForm->initialValue = true;	//Initially, the login-form should be showing
$showLoginForm->process();

//Generate login form
$loginForm = new Form('login');

$lUser = $loginForm->addInput(new TextInput('username'));
$lUser->charLimit = new Range(5, 16);
$lUser->wordLimit = new Range(1, 1);

$lPass = $loginForm->addInput(new TextInput('password'));
$lPass->charLimit = new Range(5, 16);
$lPass->wordLimit = new Range(1, 1);

$lSubmit = $loginForm->addInput(new SubmitInput('submit'));

$loginForm->onSubmit = function($vals, $errs, $form) use($app) {
	if ($errs === null) {
		$user = $app->getDb()->fetch("SELECT * FROM `User` WHERE `User`=:user AND `Password`=:pass", array(
			':user' => $vals['username'],
			':pass' => $vals['password']
		));
		
		if ($user === null) {
			$form->sessionClear();
			$app->refresh(array('notify' => 'Invalid credentials', 'err' => true));
		}
		
		$app->setUserData(array('id' => $user['ID']));
		$app->redirect('home');
	} else {
		$form->sessionClear();
		$form->retry($errs);
	}
};
$loginForm->process();

//Generate signin form
$signupForm = new Form('signup');
$sEmail = $signupForm->addInput(new EmailInput('email'));
$sSubmit = $signupForm->addInput(new SubmitInput('submit'));

$signupForm->onSubmit = function($formValues, $errs, $form) use($showLoginForm, $app) {
	if ($errs === null) {
		$sender = $app->getEmailSender();
		$email = $formValues['email'];
		$enc = encrypt($email);
		$url = 'skyladder.net/demo/qer/validate/'.$enc;
		
		$msg = "Hello,\r\n\r\n".
			"Thanks for taking an interest in Q'er!\r\n".
			"You can complete registering by visiting the following site:\r\n\r\n".
			"\t$url\r\n\r\n".
			"Thanks,\r\n".
			"-The Q'er Team";
		
		$sender->send($email, 'Signup for Q\'er', $msg);
		
		$showLoginForm->setData(true); //We want the user to redirect and see the login form
		
		$app->refresh(array('email-sent' => true));
	} else {
		$form->sessionClear();
		$form->retry($errs);
	}
};
$signupForm->process();

//A true value from $showLoginForm indicates the user is logging in, otherwise they are signing up.
$loginMode = $showLoginForm->on() ? 'login' : 'signup';
?>
<div class="page login">
	<div class="content">
		<?php if ($app->hasParam('email-sent')) { ?>
		<div id="confirm-email">
			<h1>We've sent you an email!</h1>
			<p>
				We've gotten in touch with you at the email you provided us.
				The email we sent has instructions that will allow you to create your account.
				<br/><br/>
				We think Q'er is going to exceed your expectations!
			</p>
		</div>
		<?php } ?>
		<?php if ($app->hasParam('email-unavailable')) { ?>
		<div><p>We're afraid the email you requested is already in use.</p></div>
		<?php } ?>
		<?php if ($app->hasParam('account-created')) { ?>
		<div><p>Your account has been created! You may now log in.</p></div>
		<?php } ?>
		<div class="form login-form <?php if ($loginMode === 'signup') echo 'hidden'; ?>">
			<h1>Login</h1>
			<form <?php $loginForm->wAttrs(); ?>>
				<div class="fields center">
					<div class="field">
						<input type="text" <?php $lUser->wAttrs(); ?> placeholder="Username"/>
					</div>
					<div class="field">
						<input type="password" <?php $lPass->wAttrs(); ?> placeholder="Password"/>
					</div>
					<div class="field">
						<input type="submit" <?php $lSubmit->wAttrs(); ?>/>
					</div>
				</div>
			</form>
		</div>
		<div class="form signup-form <?php if ($loginMode === 'login') echo 'hidden'; ?>">
			<h1>Signup</h1>
			<div class="signup-3rd-party controls">
				<a href="https://facebook.com" class="item-3rd-party control">
					<img src="<?php wResource('_img/home.png'); ?>" alt="facebook"/>
				</a>
				<a href="https://twitter.com" class="item-3rd-party control">
					<img src="<?php wResource('_img/home.png'); ?>" alt="twitter"/>
				</a>
				<a href="https://instagram.com" class="item-3rd-party control">
					<img src="<?php wResource('_img/home.png'); ?>" alt="instagram"/>
				</a>
			</div>
			<form <?php $signupForm->wAttrs(); ?>>
				<p>Or, sign up via email</p>
				<div class="fields center">
					<div class="field">
						<input type="text" <?php $sEmail->wAttrs(); ?> placeholder="Email"/>
					</div>
					<div class="field">
						<input type="submit" <?php $sSubmit->wAttrs(); ?>/>
					</div>
				</div>
			</form>
		</div>
		<div id="no-account-content" class="field center">
			<input type="checkbox" id="no-account" <?php if ($loginMode === 'signup') echo 'checked="checked"'; ?>/>
			<span class="label">No Account?</span>
		</div>
	</div>
</div>