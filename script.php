<?php
require_once 'vendor/autoload.php';

ini_set('max_execution_time', 86400);
ini_set('memory_limit', '-1');
ini_set('display_startup_errors',1);
ini_set('display_errors',1);
error_reporting(E_ALL);
ini_set( 'log_errors', 1 );
$debug_log_path = __DIR__ . '/debug.log';
ini_set( 'error_log',$debug_log_path  );



use Carbon\Carbon;
/**
 * @return string
 */
function do_stuff() {
	$timezone = 'America/Los_Angeles';
	try {
		$from_file     = $_FILES['file1']['tmp_name'];
		$to_file       = $_FILES['file2']['tmp_name'];
		$out_file_name = ( trim( $_POST['out_file_name'] ) ) ? trim( $_POST['out_file_name'] ) : 'output';
		$file1_columns = $_POST['columns']['file1'];
		$file2_columns = $_POST['columns']['file2'];

		/**  Identify the type of file  **/
		$File1Type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($from_file);
		/**  Create a new Reader of the type that has been identified  **/
		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($File1Type);

		/** @noinspection PhpUndefinedMethodInspection */
		$reader->setReadDataOnly(true);
		/**  Load $from_file to a Spreadsheet Object  **/
		$file1 = $reader->load($from_file);
		if ($file1->getSheetCount() === 0) {
			throw new InvalidArgumentException("the file ".$_FILES['file1']['name'] . " could not be loaded. It was recognized as a $File1Type. Try to save the spreadsheet as an ods or another type of microsoft spreadsheet" );
		}
		if ($file1->getActiveSheet()->getHighestDataRow() < 2) {
			throw new InvalidArgumentException("the file ".$_FILES['file1']['name'] . " could not access the data. It was recognized as a $File1Type. Try to save the spreadsheet as an ods or another type of microsoft spreadsheet" );
		}


		/**  Identify the type of file  **/
		$File2Type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($to_file);
		/**  Create a new Reader of the type that has been identified  **/
		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($File2Type);

		/** @noinspection PhpUndefinedMethodInspection */
		$reader->setReadDataOnly(true);
		/**  Load $to_file to a Spreadsheet Object  **/
		$file2 = $reader->load($to_file);

		if ($file2->getSheetCount() === 0) {
			throw new InvalidArgumentException("the file ".$_FILES['file2']['name'] . " could not be loaded. It was recognized as a $File2Type. Try to save the spreadsheet as an ods or another type of microsoft spreadsheet" );
		}
		if ($file2->getActiveSheet()->getHighestDataRow() < 2) {
			throw new InvalidArgumentException("the file ".$_FILES['file2']['name'] . " could not access the data. It was recognized as a $File2Type. Try to save the spreadsheet as an ods or another type of microsoft spreadsheet" );
		}


		/*
		 * link rows on each file which match with the email or the phone, make a list of the names that do not match
		 *
		 * loop through both files and get a hash of keyed by email and a hash of phone numbers (phone numbers get converted to standard format)
		 * each hash has the key  and the value is going to be the row number
		 * the root of each hash is the concat of the firstname.lastname.discipline
		 *
		 * merge each email hash, where the new values for each common key will be file1=>row#, file2=>row#
		 * do the same with the phone hash
		 *
		 * make three hashes: common, in file1 only, in file2 only :
		 *       common: the key is the row id for file 1, the value is the row id of file 2
		 *       file1 only : the key is the row id of file1, the value is true
		 *       file2 only : the key is the row id of file2, the value is true
		 *
		 * loop through combined email hash, put in one of the three hashes based on missing values of file1 and file2 keys
		 * loop through combined phone hash,
		 *       see if it matches a common, if it does do nothing, unless the common rows do not match, then flag an error because then you got bad data
		 *       see if it matches a file1 only, if there is a file2 put in common and remove from file1 only
		 *       likewise do the same for file2 only, if there is a file1 put in common and remove from file2 only
		 *
		 * Make common spreadsheet using the rows found in common
		 * Make not in other spreadsheet by using the two other "not in" hashes
		 */

		$file1_email_hash = get_single_hash($file1,$file1_columns,'email');
		$file1_phone_hash = get_single_hash($file1,$file1_columns,'phone');
		$file2_email_hash = get_single_hash($file2,$file2_columns,'email');
		$file2_phone_hash = get_single_hash($file2,$file2_columns,'phone');
		$email_hash = merge_hashes($file1_email_hash,$file2_email_hash);
		$phone_hash = merge_hashes($file1_phone_hash,$file2_phone_hash);

		$common = $file1_only = $file2_only = [];
		foreach ($email_hash as $key => $value) {
			$file1_row = $value['file1_row'];
			$file2_row = $value['file2_row'];
			if (($file1_row !== null)  && ($file2_row !== null)) {
				$common[$file1_row] = $file2_row;
			} elseif (($file1_row === null)  && ($file2_row !== null)) {
				$file2_only[$file2_row] = true;
			} elseif (($file1_row !== null)  && ($file2_row === null)) {
				$file1_only[$file1_row] = true;
			} else {
				//both are null
				throw new LogicException("both row 1 and row 2 are null");
			}
		}

		foreach ($phone_hash as $key => $value) {
			$file1_row = $value['file1_row'];
			$file2_row = $value['file2_row'];
			if (array_key_exists($file1_row,$common)) {
				$old_row = $common[$file1_row];
				if (($file2_row!== null) && ($common[$file1_row] !== $file2_row)) {
					throw new InvalidArgumentException("phone matches a different set of rows than the email of a person.
					 Email matches file 1 row: $file1_row , file 2 row: $old_row ; but phone matches file 1 row: $file1_row , file 2 row: $file2_row ");
				}
			} elseif (($file1_row!== null) && array_key_exists($file2_row,$file2_only)) {
				$common[$file1_row] = $file2_row;
				unset($file2_only[$file2_row]);
			} elseif (($file2_row!== null) && (array_key_exists($file1_row,$file1_only))) {
				$common[$file1_row] = $file2_row;
				unset($file1_only[$file1_row]);
			}
			else if (($file1_row !== null)  && ($file2_row !== null)) {
				$common[$file1_row] = $file2_row;
			} elseif (($file1_row === null)  && ($file2_row !== null)) {
				$file2_only[$file2_row] = true;
			} elseif (($file1_row !== null)  && ($file2_row === null)) {
				$file1_only[$file1_row] = true;
			} else {
				//both are null
				throw new LogicException("both row 1 and row 2 are null");
			}
		}



		$temp_dir = tempdir(null,'tmp_save_sheets_',0777);
		$phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

		/** Create a new Spreadsheet Object **/
		$common_spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

		//write header of the spreadsheet
		$out_fname_column   = 'A';
		$org_fname_column = $file1_columns['first_name'];

		$out_lname_column   = 'B';
		$org_lname_column = $file1_columns['last_name'];

		$out_email_column    = 'C';
		$org_email_column = $file1_columns['email'];

		$out_phone_column   = 'D';
		$org_phone_column = $file1_columns['phone'];

		$out_disc_column    = 'E';
		$org_disc_column = $file1_columns['discipline'];

		$out_original_class_date_column      = 'F';
		$org_class_date_column = $file1_columns['class_date'];

		$out_current_class_date_column      = 'G';
		$cur_class_date_column = $file2_columns['class_date'];

		$out_original_class_location_column  = 'H';
		$org_class_location_column  = $file1_columns['class_location'];

		$out_current_class_location_column  = 'I';
		$cur_class_location_column  = $file2_columns['class_location'];

		$out_original_options_column  = 'J';
		$org_options_column  = $file1_columns['options'];

		$out_current_options_column  = 'K';
		$cur_options_column  = $file2_columns['options'];

		$row = 1;
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_fname_column$row",'First Name');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_lname_column$row",'Last Name');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_email_column$row",'Email');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_phone_column$row",'Phone');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_disc_column$row",'Discipline');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_date_column$row",'Original Course Date');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_current_class_date_column$row",'Current Course Date');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_location_column$row",'Original Course Location');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_current_class_location_column$row",'Current Course Location');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_original_options_column$row",'Original Options');
		$common_spreadsheet->getActiveSheet()->setCellValue("$out_current_options_column$row",'Current Options');

		$common_spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('J')->setAutoSize(true);
		$common_spreadsheet->getActiveSheet()->getColumnDimension('K')->setAutoSize(true);


		foreach ($common as $original => $current) {
			$row++;
			$fname = $file1->getActiveSheet()->getCell($org_fname_column.$original);
			$lname = $file1->getActiveSheet()->getCell($org_lname_column.$original);
			$email = $file1->getActiveSheet()->getCell($org_email_column.$original);
			$phone = $file1->getActiveSheet()->getCell($org_phone_column.$original);

			if (!empty((string)$phone)) {
				try {
					$phoneNumberObject = $phoneNumberUtil->parse( (string)$phone, 'US' );
					$phone = $phoneNumberUtil->format( $phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL );
				} catch (\libphonenumber\NumberParseException $e) {
					//do nothing, just do not format the number, sometimes other things are in the phone column

				}
			}
			$disc  = $file1->getActiveSheet()->getCell($org_disc_column.$original);


			$org_class_location  = $file1->getActiveSheet()->getCell($org_class_location_column.$original);
			$org_options  = $file1->getActiveSheet()->getCell($org_options_column.$original);


			$cur_class_location  = $file2->getActiveSheet()->getCell($cur_class_location_column.$current);
			$cur_options  = $file2->getActiveSheet()->getCell($cur_options_column.$current);

			$org_class_date_raw  = (string)$file1->getActiveSheet()->getCell($org_class_date_column.$original)->getFormattedValue();
			$cur_class_date_raw  = (string)$file2->getActiveSheet()->getCell($cur_class_date_column.$current)->getFormattedValue();

			if (is_numeric($org_class_date_raw)) {
				$date_time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($org_class_date_raw);
				$start = Carbon::parse($date_time->format('m/d/Y H:i:s'),$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$org_class_date = $start->format('m/d/Y H:i');
			} else if ($org_class_date_raw) {

				$start = Carbon::parse($org_class_date_raw,$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$org_class_date = $start->format('m/d/Y H:i:s');

			} else {
				$org_class_date = '';
			}


			if (is_numeric($cur_class_date_raw)) {
				$date_time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cur_class_date_raw);
				$start = Carbon::parse($date_time->format('m/d/Y H:i:s'),$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$cur_class_date = $start->format('m/d/Y H:i');

			} else if ($cur_class_date_raw) {

				$start = Carbon::parse($cur_class_date_raw,$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$cur_class_date = $start->format('m/d/Y H:i');
			} else {
				$cur_class_date = '';
			}


			$common_spreadsheet->getActiveSheet()->setCellValue($out_fname_column.$row,$fname);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_lname_column$row",$lname);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_email_column$row",$email);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_phone_column$row",$phone);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_disc_column$row",$disc);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_date_column$row",$org_class_date);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_current_class_date_column$row",$cur_class_date);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_location_column$row",$org_class_location);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_current_class_location_column$row",$cur_class_location);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_original_options_column$row",$org_options);
			$common_spreadsheet->getActiveSheet()->setCellValue("$out_current_options_column$row",$cur_options);

		}
		/*
		 First Name
		Last Name
		Email
		Phone
		Discipline
		Original Class Date
		Current Class Date (the one from Sheet 2)
		Original Class Location
		Current Class Location (Column T from Sheet 2)
		Original Options
		Current Options (Column AA from Sheet 2)
		 */




		$common_file_name = null;
		$common_file_path = null;
		if ($File1Type === 'Csv' || $File2Type === 'Csv') {
			//make a csv
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($common_spreadsheet);
			$writer->setDelimiter(',');
			$writer->setEnclosure('"');
			$writer->setLineEnding("\n");
			$writer->setSheetIndex(0);
			$common_file_name = "renewed.csv";
			$common_file_path = "$temp_dir/$common_file_name";
			$writer->save($common_file_path);
		} else {
			//make a spreadsheet
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($common_spreadsheet);
			$common_file_name = "renewed.xlsx";
			$common_file_path = "$temp_dir/$common_file_name";
			$writer->save($common_file_path);
		}


		///make not available in both sheets
		/*
		 *  First Name
			Last Name
			Email
			Phone
			Discipline
			Original Class Date
			Original Class Location
			Options
		*/

		/** Create a new Spreadsheet Object **/
		$sep_spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

		//write header of the spreadsheet
		$out_fname_column   = 'A';
		$org_fname_column = $file1_columns['first_name'];

		$out_lname_column   = 'B';
		$org_lname_column = $file1_columns['last_name'];

		$out_email_column    = 'C';
		$org_email_column = $file1_columns['email'];

		$out_phone_column   = 'D';
		$org_phone_column = $file1_columns['phone'];

		$out_disc_column    = 'E';
		$org_disc_column = $file1_columns['discipline'];

		$out_original_class_date_column      = 'F';
		$org_class_date_column = $file1_columns['class_date'];

		$out_original_class_location_column  = 'G';
		$org_class_location_column  = $file1_columns['class_location'];

		$out_original_options_column  = 'H';
		$org_options_column  = $file1_columns['options'];





		//$org_class_column = $file1_columns['course'];


		$row = 1;
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_fname_column$row",'First Name');
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_lname_column$row",'Last Name');
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_email_column$row",'Email');
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_phone_column$row",'Phone');
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_disc_column$row",'Discipline');
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_date_column$row",'Course Date');
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_location_column$row",'Course Location');
		$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_options_column$row",'Options');


		$sep_spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$sep_spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$sep_spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$sep_spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
		$sep_spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
		$sep_spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
		$sep_spreadsheet->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
		$sep_spreadsheet->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);


		foreach ($file1_only as $current => $b_always_true) {
			$row++;
			$fname = $file1->getActiveSheet()->getCell($org_fname_column.$current);
			$lname = $file1->getActiveSheet()->getCell($org_lname_column.$current);
			$email = $file1->getActiveSheet()->getCell($org_email_column.$current);
			$phone = $file1->getActiveSheet()->getCell($org_phone_column.$current);
			if (!empty((string)$phone)) {
				try {
					$phoneNumberObject = $phoneNumberUtil->parse( (string)$phone, 'US' );
					$phone = $phoneNumberUtil->format( $phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL );
				} catch (\libphonenumber\NumberParseException $e) {
					//do nothing, just do not format the number, sometimes other things are in the phone column

				}
			}

			$disc  = $file1->getActiveSheet()->getCell($org_disc_column.$current);

			$org_class_date_raw  = $file1->getActiveSheet()->getCell($org_class_date_column.$current)->getFormattedValue();
			$org_class_location  = $file1->getActiveSheet()->getCell($org_class_location_column.$current);
			$org_options  = $file1->getActiveSheet()->getCell($org_options_column.$current);

			if (is_numeric($org_class_date_raw)) {
				$date_time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($org_class_date_raw);
				$start = Carbon::parse($date_time->format('m/d/Y H:i:s'),$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$org_class_date = $start->format('m/d/Y H:i');
			} else if ($org_class_date_raw) {

				$start = Carbon::parse($org_class_date_raw,$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$org_class_date = $start->format('m/d/Y H:i');
			} else {
				$org_class_date = '';
			}


			$sep_spreadsheet->getActiveSheet()->setCellValue($out_fname_column.$row,$fname);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_lname_column$row",$lname);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_email_column$row",$email);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_phone_column$row",$phone);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_disc_column$row",$disc);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_date_column$row",$org_class_date);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_location_column$row",$org_class_location);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_options_column$row",$org_options);

		}


		foreach ($file2_only as $current => $b_always_true) {
			$row++;
			$fname = $file1->getActiveSheet()->getCell($org_fname_column.$current);
			$lname = $file1->getActiveSheet()->getCell($org_lname_column.$current);
			$email = $file1->getActiveSheet()->getCell($org_email_column.$current);
			$phone = $file1->getActiveSheet()->getCell($org_phone_column.$current);

			if (!empty((string)$phone)) {
				try {
					$phoneNumberObject = $phoneNumberUtil->parse( (string)$phone, 'US' );
					$phone = $phoneNumberUtil->format( $phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL );
				} catch (\libphonenumber\NumberParseException $e) {
					//do nothing, just do not format the number, sometimes other things are in the phone column

				}
			}

			$disc  = $file1->getActiveSheet()->getCell($org_disc_column.$current);

			$org_class_date_raw  = $file1->getActiveSheet()->getCell($org_class_date_column.$current)->getFormattedValue();
			$org_class_location  = $file1->getActiveSheet()->getCell($org_class_location_column.$current);
			$org_options  = trim($file1->getActiveSheet()->getCell($org_options_column.$current));

			if (is_numeric($org_class_date_raw)) {
				$date_time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($org_class_date_raw);
				$start = Carbon::parse($date_time->format('m/d/Y H:i:s'),$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$org_class_date = $start->format('m/d/Y H:i');
			} else if ($org_class_date_raw) {

				$start = Carbon::parse($org_class_date_raw,$timezone);
				if ($start->second === 59) {
					$start->addSecond(1); //rounding from spreadsheet
				}
				$org_class_date = $start->format('m/d/Y H:i');
			} else {
				$org_class_date = '';
			}







			$sep_spreadsheet->getActiveSheet()->setCellValue($out_fname_column.$row,$fname);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_lname_column$row",$lname);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_email_column$row",$email);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_phone_column$row",$phone);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_disc_column$row",$disc);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_date_column$row",$org_class_date);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_class_location_column$row",$org_class_location);
			$sep_spreadsheet->getActiveSheet()->setCellValue("$out_original_options_column$row",$org_options);


		}


		$seperate_file_name = null;
		$seperate_file_path = null;
		if ($File1Type === 'Csv' || $File2Type === 'Csv') {
			//make a csv
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($sep_spreadsheet);
			$writer->setDelimiter(',');
			$writer->setEnclosure('"');
			$writer->setLineEnding("\n");
			$writer->setSheetIndex(0);
			$seperate_file_name = "yet_to_renew.csv";
			$seperate_file_path = "$temp_dir/$seperate_file_name";
			$writer->save($seperate_file_path);
		} else {
			//make a spreadsheet
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sep_spreadsheet);

			$seperate_file_name = "yet_to_renew.xlsx";
			$seperate_file_path = "$temp_dir/$seperate_file_name";
			$writer->save($seperate_file_path);
		}

		//zip up directory contents to a temp file
		$temp_zip_file_path = tempnam(sys_get_temp_dir(), 'zip_results_');
		$zip = new ZipArchive();
		$b_what = $zip->open($temp_zip_file_path);
		if (!$b_what) {
			throw new Exception("Could not open zip file at $temp_zip_file_path");
		}
		$b_what = $zip->addFile($common_file_path,$common_file_name);
		if (!$b_what) {
			throw new Exception("Could not Add  $common_file_name file at $common_file_path inside $temp_zip_file_path");
		}

		$b_what = $zip->addFile($seperate_file_path,$seperate_file_name);
		if (!$b_what) {
			throw new Exception("Could not Add  $common_file_name file at $common_file_path inside $temp_zip_file_path");
		}


		$b_what = $zip->close();
		if (!$b_what) {
			throw new Exception("Could not write the zip file at $temp_zip_file_path");
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename=' . $out_file_name . '.zip' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $temp_zip_file_path ) );
		header( 'X-Accel-Buffering: no' );

		//ob_clean();
		//flush();
		set_time_limit( 0 );
		$file = @fopen( $temp_zip_file_path, "rb" );
		while ( ! feof( $file ) ) {
			print( @fread( $file, 1024 * 8 ) );
			ob_flush();
			flush();
		}
		if (  $temp_dir ) {
			exec( "rm -rf $temp_dir", $output, $status );
			if ( $status !== 0 ) {
				error_log( "Could not delete temp directory at $temp_dir");

			}
		}


		exit;
	} catch (Exception $e) {
		$da = $e->getMessage() ."\n" . $e->getFile() .' line' . $e->getLine() . "\n" . $e->getTraceAsString();
		error_log($da);
		return $da;
	}
}

/**
 * @param PhpOffice\PhpSpreadsheet\Spreadsheet $file
 * @param array $column_indexes
 * @param string $special_col_name
 * @throws PhpOffice\PhpSpreadsheet\Exception
 * @return int[] , keys are strings, values are row indexes
 */
function get_single_hash($file,$column_indexes,$special_col_name) {
	$ret = [];
	$phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
	$rows_max = $file->getActiveSheet()->getHighestDataRow();
	$fname_column = $column_indexes['first_name'];
	$lname_column = $column_indexes['last_name'];
	$disc_column = $column_indexes['discipline'];
	$email_column = $column_indexes['email'];
	$phone_column = $column_indexes['phone'];
	if ($special_col_name === 'email') {
		$special_column = $email_column;
	} elseif ($special_col_name === 'phone') {
		$special_column = $phone_column;
	} else {
		throw new LogicException("Special is neither email or phone");
	}
	for($row = 2; $row<= $rows_max; $row++) {
		$fname = trim($file->getActiveSheet()->getCell("$fname_column$row"));
		$lname = trim($file->getActiveSheet()->getCell("$lname_column$row"));
		$disc = trim($file->getActiveSheet()->getCell("$disc_column$row"));
		$root_key = $fname.'-'.$lname.'-'.$disc;
		$special = trim($file->getActiveSheet()->getCell("$special_column$row"));
		if (empty($special)) {continue;}
		if ($special_col_name === 'phone') {
			try {
				$phoneNumberObject = $phoneNumberUtil->parse( $special, 'US' );
				$phone_number_e164 = $phoneNumberUtil->format( $phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164 );
				$special           = $phone_number_e164;
			} catch (\libphonenumber\NumberParseException $e) {
				//do nothing, just do not format the number, sometimes other things are in the phone column
			}
		}
		$key = $root_key . '-'.$special;
		$ret[$key] = $row;
	}
	return $ret;
}

/**
 * @param int[] $hash_file1
 * @param int[] $hash_file2
 * @throws Exception
 * @return object[] - array keyed by string
 */
function merge_hashes($hash_file1,$hash_file2) {

	$ret = [];

	foreach($hash_file1 as $key => $row_number) {

		if (!array_key_exists($key,$ret)) {
			$ret[$key] = ['file1_row'=>$row_number,'file2_row'=>null];
		}

		if (array_key_exists($key,$hash_file2)) {
			$file2_row = $hash_file2[$key];
			$ret[$key]['file2_row'] = $file2_row;
		}
	}

	foreach ($hash_file2 as $key =>$row_number) {
		if (!array_key_exists($key,$ret)) {
			$ret[$key] = ['file1_row'=>null,'file2_row'=>$row_number];
		} else {
			//check to make sure rows match
			$should_be_row = $ret[$key]['file2_row'];
			if ($should_be_row !== $row_number) {
				throw new Exception("rows don't match when merging keys");
			}
		}

	}
	return $ret;

}




/**
 * Creates a random unique temporary directory, with specified parameters,
 * that does not already exist (like tempnam(), but for dirs).
 *
 * Created dir will begin with the specified prefix, followed by random
 * numbers.
 *
 * @link https://php.net/manual/en/function.tempnam.php
 *
 * @param string|null $dir Base directory under which to create temp dir.
 *     If null, the default system temp dir (sys_get_temp_dir()) will be
 *     used.
 * @param string $prefix String with which to prefix created dirs.
 * @param int $mode Octal file permission mask for the newly-created dir.
 *     Should begin with a 0.
 * @param int $maxAttempts Maximum attempts before giving up (to prevent
 *     endless loops).
 * @return string|bool Full path to newly-created dir, or false on failure.
 */
function tempdir($dir = null, $prefix = 'tmp_', $mode = 0700, $maxAttempts = 1000)
{
	/* Use the system temp dir by default. */
	if (is_null($dir))
	{
		$dir = sys_get_temp_dir();
	}

	/* Trim trailing slashes from $dir. */
	$dir = rtrim($dir, DIRECTORY_SEPARATOR);

	/* If we don't have permission to create a directory, fail, otherwise we will
	 * be stuck in an endless loop.
	 */
	if (!is_dir($dir) || !is_writable($dir))
	{
		return false;
	}

	/* Make sure characters in prefix are safe. */
	if (strpbrk($prefix, '\\/:*?"<>|') !== false)
	{
		return false;
	}

	/* Attempt to create a random directory until it works. Abort if we reach
	 * $maxAttempts. Something screwy could be happening with the filesystem
	 * and our loop could otherwise become endless.
	 */
	$attempts = 0;
	do
	{
		$path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, mt_rand(100000, mt_getrandmax()));
	} while (
		!mkdir($path, $mode) &&
		$attempts++ < $maxAttempts
	);

	return $path;
}




