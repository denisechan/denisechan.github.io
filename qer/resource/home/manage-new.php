<?php
$app = App::$instance;
$app->addLook('home/manage-new-look.css');
$app->addDomFeel('home/manage-new-feel.js');

$form = new Form('new-lineup-form');
$form->dbName = 'Lineup'; //This form populates the Lineup table

$owner = $form->addInput(new HardInput('owner', $app->user->getId()));
$owner->dbName = 'Owner_Ref';

$time = $form->addInput(new HardInput('timestamp', date('Y-m-d H:i:s')));
$time->dbName = 'Timestamp';

$name = $form->addInput(new TextInput('name'));
$name->dbName = 'Name';
$name->charLimit = new Range(3, 24);

$desc = $form->addInput(new TextInput('desc'));
$desc->dbName = 'Description';
$desc->charLimit = new Range(5, 500);
$desc->wordLimit = new Range(3, 100);

$location = $form->addInput(new TextInput('location'));
$location->dbName = 'Location';
$location->charLimit = new Range(3, 30);

$template = new TextInput('keyword-{n}');
$keywords = $form->addInput(new ListInput('keywords', $template));
$keywords->dbName = 'Keywords';

$submit = $form->addInput(new SubmitInput('submit'));

$form->onSubmit = function($formValues, $errs, $form) use($app, $name) {
	if (empty($errs)) {
		$form->dbPush();
		$app->redirect('manage/view', array('new-lineup' => $name->getValue()));
	} else {
		$form->retry($errs);
	}
};
$form->process();
?>
<div class="page new-lineup">
	<div class="content">
		<div class="form create-line">
			<h1>Create new lineup</h1>
			<form <?php $form->wAttrs(); ?>>
				<div class="fields">
					<div class="field">
						<input type="text" <?php $name->wAttrs(); ?> placeholder="Lineup Name"/>
					</div>
					<div class="field long">
						<textarea <?php $desc->wAttrs(false); ?> placeholder="Lineup Description"><?php $desc->wValue(); ?></textarea>
					</div>
					<div class="field">
						<input type="text" <?php $location->wAttrs(); ?> placeholder="Lineup Location"/>
					</div>
					<div class="field long keywords">
						<h1>Keywords <?php echo $form->getInput('keywords')->length; ?></h1>
						<?php $list = $form->getInput('keywords'); $len = $list->length; ?>
						<div class="fields">
							<?php for ($i = 0; $i < $len; $i++) { ?>
							<div class="field keyword">
								<input type="text" <?php $list->getNthInput($i)->wAttrs(); ?> placeholder="Keyword"/>
								<button class="remove<?php if ($len === 1) echo ' disabled'; ?>" <?php $list->getNthRemoveButton($i)->wAttrs(); ?>>X</button>
							</div>
							<?php } ?>
						</div>
						<button class="add" <?php $list->getAddButton($i)->wAttrs(); ?>>Add Keyword</button>
					</div>
					<div class="field">
						<input type="submit" <?php $submit->wAttrs(); ?>/>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>