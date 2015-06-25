<?php 
if ($_GET['e'] == 5) $error = "You need to login to use Q'er";

if ($_POST['username']) {

	$db = mysql_connect("localhost","qer","123456") or die("Invalid connection" . mysql_error());
	mysql_select_db("qer",$db);

	$query = "SELECT * FROM `users` WHERE `username` LIKE '".$_POST['username']."' AND `password` LIKE '".$_POST['password']."'";

	$result = mysql_query($query,$db) or die(mysql_error());

	if (mysql_num_rows($result)) {

		setcookie("user",$_POST['username'], time() + (30 * 60 * 60 * 24) );
		setcookie("pass",$_POST['password'], time() + (30 * 60 * 60 * 24) );

		header("Location: main.php");

	} else {
		$error = "Login failed. Check username and password.";
	}

} 
include("thead.php"); ?>

		<div class="row">
	  		<div id="log" class="col-xs-6 col-md-offset-4 col-sm-4">
	  			<h1 class="col-sm-offset-4">Login</h1>

	  			<?php if ($error) { ?>

	  			<div class="alert alert-danger">
	  				<p><?=$error?></p>
	  			</div>

	  			<?php } ?>

	  			<form name="login" class="form-horizontal" action="login.php" onsubmit="return validateForm()" method="post"> 
					<select id="school" class="form-control col-sm-offset-1">
						<option value="Ryerson">Ryerson University</option>
						<option>University of Toronto</option>
						<option>York University</option>
						<option>Centennial College</option>
						<option>Seneca College</option>
					</select>
					<div class="form-group">
				    	<label for="inputEmail3" class="col-sm-1 control-label"></label>
				    	<div class="col-sm-9">
				      	<input type="username" name="username" class="form-control" id="inputEmail3" placeholder="Username" required>
				    	</div>
				 	</div>
				 	<div class="form-group">
				    	<label for="inputPassword3" class="col-sm-1 control-label"></label>
				    	<div class="col-sm-9">
				      	<input type="password" class="form-control" name="password" id="inputPassword3" placeholder="Password" required>
				    	</div>
				  	</div>
				  	<div class="form-group">
				    	<div class="col-sm-offset-1 col-sm-10">
				      		<div class="checkbox">
				        		<label>
				          			<input type="checkbox"> Remember me
				        		</label>
				     		</div>
				    	</div>
				  	</div>
				  	<div class="form-group">
				    	<div class="col-sm-offset-4 col-sm-10">
				      		<button type="submit" value="submit" class="btn btn-default">Log in</button>
				    	</div>
				  	</div>
				</form>

	  		</div>
	 	</div>

	</body>

<html>