<?php
$errors = '';
if ($_POST) {
	require_once 'script.php';
	$errors = do_stuff(); //may or may not exit
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/gh/jboesch/Gritter@1.7.4/css/jquery.gritter.css" />
	<link rel="stylesheet" href="assets/bootstrap/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.css">
	<link rel="stylesheet" type="text/css" href="assets/css/dataTables.bootstrap.css">
	<link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
	<title>Script</title>
	<script type="text/javascript" src="assets/js/jquery.js"></script>
	<script type="text/javascript" src="assets/bootstrap/bootstrap.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js"></script>
	<script type="text/javascript" src="https://cdn.jsdelivr.net/gh/jboesch/Gritter@1.7.4/js/jquery.gritter.js"></script>
	<script src="https://cdn.ckeditor.com/4.7.3/standard/ckeditor.js"></script>
	<script type="text/javascript" src="assets/js/jquery.validate.js"></script>
	<script type="text/javascript" src="assets/js/additional-methods.js"></script>
	<script type="text/javascript" src="assets/js/scripts.js"></script>
</head>
<?php
    $fields = [
        ['start'=> 'C', 'name'=>'First Name','element'=>'first_name'],
        ['start'=> 'B', 'name'=>'Last Name','element'=>'last_name'],
	    ['start'=> 'D', 'name'=>'Email','element'=>'email'],
        ['start'=> 'K', 'name'=>'Phone','element'=>'phone'],
        ['start'=> 'R', 'name'=>'Course','element'=>'course'],
	    ['start'=> 'S', 'name'=>'Class Date','element'=>'class_date'],
        ['start'=> 'T', 'name'=>'Class Location','element'=>'class_location'],
	    ['start'=> 'W', 'name'=>'Discipline','element'=>'discipline'],
        ['start'=> 'AA', 'name'=>'Options','element'=>'options']
    ];

    $options = [];
    for($i=0; $i<52; $i++) {
        $ord_val = $i % 26 + ord('A');
        $character = chr($ord_val);
        if ($i >=26) {
	        $character = 'A' . $character;
        }
        $options[$character] = $character;
    }

?>
<body>
    <?php if ($errors): ?>
    <div class="row">
        <div class="col-md-offset-3 col-md-6">
            <pre>
                <?= $errors ?>
            </pre>
        </div>
    </div>
    <?php endif; ?>
	<!-- MultiStep Form -->
	<div class="row">
		<div class="col-md-6 col-md-offset-3">
			<form id="msform" method="post" action="index.php" enctype="multipart/form-data" target="_blank">
				<!-- progressbar -->
				<ul id="progressbar">
					<li class="active">ORIGINAL CLASS REPORT</li>
					<li>CURRENT PERIOD REGISTRATION REPORT</li>
				</ul>
				<!-- fieldsets -->
				<fieldset class="section-a">
					<h2 class="fs-title">Original Class Report (XLS, XLSX or CSV)</h2>
                    <input type="file" name="file1"/>
                    <div>
                    <?php foreach ($fields as $field) { ?>
                        <div class="form-group" style="text-align: left">
                            <label style="margin-left: 0.5em" for="columns[file1][<?= $field['element'] ?>]">
                                <?= strtoupper($field['name']) ?> Column
                            </label>
                            <select
                                    class="form-control" style="padding-top: 0; padding-bottom: 0"
                                    id="columns[file1][<?= $field['element'] ?>]"
                                    name="columns[file1][<?= $field['element'] ?>]">
                                <option  disabled value="-1"><?= strtoupper($field['name']) ?>: Column in CSV 1</option>
                                <?php

                                foreach ($options as $key => $value) :?>
                                    <?php if ($field['start'] === $key) { ?>
                                        <option value="<?php echo $key; ?>"  selected ><?php echo $value; ?></option>
                                    <?php } else { ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php }  ?>
                                <?php endforeach;?>
                            </select>
                        </div>
                    <?php } ?>
                    </div>
<!--					<div class="from-group text-left">-->
<!--						<label class="checkbox-inline" for="form_file_checkbox"><input type="checkbox" name="form_file_checkbox" id="form_file_checkbox">Clean up the Tel Numbers (spaces, alphabets & symbols will be removed leaving only digits before comparing)</label>-->
<!--					</div>-->
					<input type="button" name="next" class="next action-button" data-section="a" value="Next"/>
				</fieldset>
				<fieldset class="section-b">
					<h2 class="fs-title">Current Period Registration Report (XLS, XLSX or CSV)</h2>
                    <input type="file" class="to_file" name="file2"/>
                    <input type="text" class="form-control" name="out_file_name" placeholder="Name of output zip file? Will be 'output' if blank" />

                    <div>
						<?php foreach ($fields as $field) { ?>
                            <div class="form-group" style="text-align: left">
                                <label  style="margin-left: 0.5em" for="columns[file2][<?= $field['element'] ?>]">
									<?= strtoupper($field['name']) ?> Column
                                </label>
                                <select
                                        class="form-control" style="padding-top: 0; padding-bottom: 0"
                                        id="columns[file2][<?= $field['element'] ?>]"
                                        name="columns[file2][<?= $field['element'] ?>]">
                                    <option  disabled value="-1"><?= strtoupper($field['name']) ?>: Column in CSV 1</option>
									<?php

									foreach ($options as $key => $value) :?>
										<?php if ($field['start'] === $key) { ?>
                                            <option value="<?php echo $key; ?>"  selected ><?php echo $value; ?></option>
										<?php } else { ?>
                                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
										<?php }  ?>
									<?php endforeach;?>
                                </select>
                            </div>
						<?php } ?>
                    </div>

<!--					<div class="from-group text-left">-->
<!--						<label class="checkbox-inline" for="to_file_checkbox"><input type="checkbox" name="to_file_checkbox" id="to_file_checkbox">Clean up the Tel Numbers (spaces, alphabets & symbols will be removed leaving only digits before comparing)</label>-->
<!--					</div>-->
					<input type="button" name="previous" class="previous action-button-previous" value="Previous"/>
					<input type="submit" name="submit" class="submit action-button" data-section="b" value="Submit"/>
				</fieldset>				
			</form>
		</div>
	</div>
	<!-- /.MultiStep Form -->

</body>
</html>