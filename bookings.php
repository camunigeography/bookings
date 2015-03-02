<?php

# Class to provide a system for booking availability slots
require_once ('frontControllerApplication.php');
class bookings extends frontControllerApplication
{
	# Class properties
	private $firstPrivateDate = false;
	
	
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName' => 'Booking request',
			'username' => 'bookings',
			'password' => NULL,
			'database' => 'bookings',
			'table' => 'bookings',
			'administrators' => true,
			'settings' => true,
			'serverAdministrator'	=> NULL,	// E-mail address of the server administrator
			'recipient'	=> NULL,	// Who to send the e-mail requests to
			'form' => true,
			'div' => 'bookings',
			'tablePrefix' => false,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available tasks
		$actions = array (
			'home' => array (
				'description' => 'Booking request',
				'url' => '',
				'tab' => 'Bookings',
				'icon' => 'clock',
			),
			'edit' => array (
				'description' => 'Booking requests - edit',
				'url' => 'edit.html',
				'tab' => 'Edit',
				'administrator' => true,
				'icon' => 'pencil',
			),
			'request' => array (
				'description' => 'Make a booking request',
				'url' => 'request/%1/%2/',
				'usetab' => 'home',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Additional processing
	public function main ()
	{
		# Load required libraries
		require_once ('timedate.php');
		
		# Process the place titles
		if ($this->action != 'settings') {
			$placeTitles = explode ("\n", $this->settings['placeTitles']);
			$this->settings['placeTitles'] = array ();
			foreach ($placeTitles as $index => $placeTitle) {
				$place = $index + 1;	// Indexed from 1; the numeric value is what is stored, not the label
				$this->settings['placeTitles'][$place] = trim ($placeTitle);
			}
		}
		
		# Get the dates
		$this->dates = $this->getDates ();
		
		# Get the data or end
		if (!$this->data = $this->getData ()) {return false;}
		
	}
	
	
	# Function to get the dates for future months
	public function getDates ()
	{
		# Create an array of dates in future months
		$dates = timedate::getDatesForFutureMonths ($this->settings['listMonthsAheadPublic'], 'Y-m-d', $removeWeekends = true);
		
		# If the user is an admin, show the fuller list, and determine the first date that is private
		if ($this->userIsAdministrator) {
			$datesPublic = $dates;
			$dates = timedate::getDatesForFutureMonths ($this->settings['listMonthsAheadPrivate'], 'Y-m-d', $removeWeekends = true);
			$privateDates = array_diff ($dates, $datesPublic);
			$this->firstPrivateDate = reset (array_values ($privateDates));
		}
		
		# Remove earliest dates
		for ($i = 0; $i < $this->settings['excludeNextDays']; $i++) {
			array_shift ($dates);
		}
		
		# Return the dates
		return $dates;
	}
	
	
	# Function to get the data
	private function getData ()
	{
		# Determine the first and last dates, so that only this range is obtained for efficiency
		$firstDate = $this->dates[0];
		$untilDate = end (array_values ($this->dates));
		
		# Get any data for between these ranges
		$query = "SELECT
			*
			FROM {$this->settings['database']}.{$this->settings['table']}
			WHERE
				    `date` >= '{$firstDate}'
				AND `date` <= '{$untilDate}'
			ORDER BY `date`, place
		;";
		$rawdata = $this->databaseConnection->getData ($query);
		
		# Regroup by date then place
		$data = array ();
		foreach ($rawdata as $booking) {
			$date = $booking['date'];
			$place = $booking['place'];
			$approved = ($booking['approved'] ? 'approved' : 'unapproved');
			$data[$date][$place][$approved][] = $booking;
		}
		
		// application::dumpData ($data);
		
		# Return the data
		return $data;
	}
	
	
	# Home/listing page
	public function home ()
	{
		# Start the HTML
		$html  = $this->settings['introductoryTextHtml'];
		
		# Button and introduction for admins
		if ($this->userIsAdministrator) {
			$html .= "\n" . '<p class="actions right"><a href="' . $this->baseUrl . '/edit.html"><img src="/images/icons/pencil.png" alt=""> Edit</a></p>';
			$html .= "\n" . '<p><img src="/images/icons/asterisk_yellow.png" alt="Info" class="icon" /> As an Administrator, you can hover the mouse over any booked slot to see details.</p>';
		}
		
		# Show the table
		$html .= $this->listingTable ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Editing page
	public function edit ()
	{
		# Start the HTML
		$html  = '';
		
		# Button to return to viewing
		$html .= "\n" . '<p class="actions right"><a href="' . $this->baseUrl . '/"><img src="/images/icons/cross.png" alt=""> Cancel editing</a></p>';
		
		# Assemble the listing template
		$listingTableTemplate = $this->listingTable ($editMode = true, $formElements);
		
		# Create a form
		$form = new form (array (
			'display'		=> 'template',
			'displayTemplate' => '{[[PROBLEMS]]}<p>{[[SUBMIT]]}</p>' . $listingTableTemplate . '<p>{[[SUBMIT]]}</p>',
			'unsavedDataProtection' => true,
			'reappear' => true,
			'formCompleteText' => false,
		));
		foreach ($formElements as $fieldname => $default) {
			$form->input (array (
				'name'	=> $fieldname,
				'title'	=> 'Status',
				'required' => false,
				'default' => $default,
				'size' => 23,
			));
		}
		if ($result = $form->process ($html)) {
			
			# Determine the changed fields, for efficiency
			$changedFields = application::array_changed_values_fields ($formElements, $result);
			if ($changedFields) {
				
				# Insert/update the changes
				foreach ($changedFields as $field) {
					
					# Determine the match for existing data, and set what the new record should become
					list ($date, $place) = explode ('_', $field, 2);
					$where = array (
						'date' => $date,
						'place' => $place,
						'approved' => '1',
					);
					$data = $where;	// Clone
					$data['reservation'] = $result[$field];
					
					# Insert/update the changes, or delete the record if no text
					$existingRecord = $this->databaseConnection->selectOne ($this->settings['database'], $this->settings['table'], $where);
					if ($existingRecord) {
						if (empty ($data['reservation'])) {
							$this->databaseConnection->delete ($this->settings['database'], $this->settings['table'], array ('id' => $existingRecord['id']));
						} else {
							$this->databaseConnection->update ($this->settings['database'], $this->settings['table'], $data, array ('id' => $existingRecord['id']));
						}
					} else {
						$this->databaseConnection->insert ($this->settings['database'], $this->settings['table'], $data, false);
					}
				}
				
				# Insert confirmation into the start of the page
				$confirmationHtml = "\n<div class=\"graybox\">\n\t<p class=\"success\"><img src=\"/images/icons/tick.png\" alt=\"Tick\" class=\"icon\" /> <strong>The " . (count ($changedFields) == 1 ? 'change has' : 'changes have') . " now been made, as shown below. <a href=\"{$this->baseUrl}/\">Return to public listing</a></strong> or edit further below.</p>\n</div>";
				$highlightIds = array ();
				foreach ($changedFields as $field) {
					$highlightIds[] = '#form_' . $field;
				}
				$cssHtml = "\n" . '<style type="text/css">' . implode (', ', $highlightIds) . ' {border: 2px solid green; background-color: #e0eedf;}</style>';
				$html = $cssHtml . $confirmationHtml . $html;
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to generate the listing table
	public function listingTable ($editMode = false, &$formElements = array ())
	{
		# Assemble the data for a table, looping through the dates, so that all are shown, irrespective of whether a booking is present
		$table = array ();
		foreach ($this->dates as $date) {
			
			# Set the key for this row, which will be used as the class for this row
			$key = 'week-' . $date;
			
			# Determine if this is Monday
			$isMonday = date ('N', strtotime ($date)) == '1';
			if ($isMonday) {$key .= ' newweek';}
			
			# If this is the first private date, add an extra class
			if ($date == $this->firstPrivateDate) {
				$table['firstprivatedate'] = array ('date' => 'Dates from here are not yet public');
				foreach ($this->settings['placeTitles'] as $place => $label) {
					$table['firstprivatedate'][$place] = '';
				}
			}
			
			# Get the formatted date and set this as the first column
			$table[$key]['date'] = date ('l, jS F Y', strtotime ($date));
			
			# Determine whether the institution is closed this day
			$firstPlace = 1;
			$isClosedToday = (isSet ($this->data[$date]) && isSet ($this->data[$date][$firstPlace]) && isSet ($this->data[$date][$firstPlace]['approved']) && isSet ($this->data[$date][$firstPlace]['approved'][0]) && (strtolower (trim ($this->data[$date][$firstPlace]['approved'][0]['reservation'])) == 'closed'));
			
			# Determine the data for each place
			foreach ($this->settings['placeTitles'] as $place => $label) {
				
				# If closed, state this
				if ($isClosedToday && !$editMode) {
					$table[$key][$place] = '<span class="booked">Closed this day</span>';
				} else {
					$isBooked = (isSet ($this->data[$date]) && isSet ($this->data[$date][$place]) && isSet ($this->data[$date][$place]['approved']) && isSet ($this->data[$date][$place]['approved'][0]));
					$urlMoniker = str_replace ('-', '', $date);
					$isMorning = (substr_count (strtolower ($label), 'morning'));
					$bookedFor = ($this->userIsAdministrator && $isBooked ? ' title="' . htmlspecialchars ($this->data[$date][$place]['approved'][0]['reservation']) . '"' : '');
					$linkStart = "<a rel=\"nofollow\" href=\"{$this->baseUrl}/request/{$urlMoniker}/" . ($isMorning ? 'morning' : 'afternoon') . '/">';
					if ($editMode) {
						$fieldname = $date . '_' . $place;
						$formElements[$fieldname] = ($isBooked ? $this->data[$date][$place]['approved'][0]['reservation'] : '');
						$table[$key][$place] = '{' . $fieldname . '}';
					} else {
						$table[$key][$place] = (($this->userIsAdministrator || !$isBooked) ? $linkStart : '') . ($isBooked ? "<span class=\"booked\"{$bookedFor}>Booked</span>" : "{$linkStart}Available") . (($this->userIsAdministrator || !$isBooked) ? '</a>' : '');
					}
				}
			}
		}
		
		# Compile as HTML
		$html = application::htmlTable ($table, $this->settings['placeTitles'], 'lines bookingslist', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true, $showColons = true, false, $addRowKeyClasses = true);
		
		# Return the HTML
		return $html;
	}
	
	
	
	# Function to provide a request form
	public function request ()
	{
		# Start the HTML
		$html = '';
		
		# Ensure a date is set
		if (!isSet ($_GET['date'])) {
			echo "<p class=\"warning\">You didn't specify a date.</p>";
			return false;
		}
		
		# Ensure it is a valid date by adding the hyphens in then checking it is in the generated list of dates
		list ($year, $month, $day) = sscanf ($_GET['date'], "%4s%2s%2s");
		$date = "{$year}-{$month}-{$day}";
		if (!in_array ($date, $this->dates)) {
			echo "<p class=\"warning\">The date you selected is not valid. Please select one from the list below:</p>";
			return false;
		}
		
		# Ensure a period is set
		if (!isSet ($_GET['period'])) {
			echo "<p class=\"warning\">You didn't specify a period.</p>";
			return false;
		}
		$period = $_GET['period'];
		
		# Determine the date as a string
		$dateString = timedate::convertBackwardsDateToText ($date);
		
		# Start a form
		$form = new form (array (
			'displayRestrictions' => false,
			'displayDescriptions' => true,
			'formCompleteText' => false,
			'antispam' => true,
			'div' => 'lines horizontalonly bookingform',
			'autofocus' => true,
		));
		
		# Determine the form fields
		$form->heading ('p', application::htmlUl (array ("<a href=\"{$this->baseUrl}/\">Back to list of dates</a>")));
		$form->heading ('', $this->settings['bookingPageTextHtml']);
		$form->heading (2, "Request a booking for: the <u>{$period}</u> of <u>{$dateString}</u>");
		
		$form->input (array (
			'name'			=> 'name',
			'title'			=> 'Your name',
			'required'		=> true,
		));
		$form->input (array (
			'name'			=> 'institution',
			'title'			=> 'Your institution',
			'description'	=> 'Please state the academic institution from which you are applying.',
			'required'		=> true,
		));
		$form->textarea (array (
			'name'			=> 'documents',
			'title'			=> 'Which documents do you wish to see?',
			'required'		=> true,
			'cols'			=> 40,
		));
		$form->email (array (
			'name'			=> 'email',
			'title'			=> 'E-mail address',
			'description'	=> 'Correspondence by e-mail is preferred where possible.',
			'required'		=> false,
		));
		$form->textarea (array (
			'name'			=> 'address',
			'title'			=> 'Your address',
			'required'		=> true,
			'cols'			=> 40,
			'rows'			=> 3,
		));
		$form->input (array (
			'name'			=> 'phone',
			'title'			=> 'Phone number',
			'required'		=> false,
		));
		$form->input (array (
			'name'			=> 'arrivaltime',
			'title'			=> 'Arrival time on first day',
			'required'		=> true,
		));
		$form->textarea (array (
			'name'			=> 'subsequentdays',
			'title'			=> 'Any subsequent (single/half-) days?',
			'description'	=> "If you need to stay for more than just the {$period} of {$dateString}, please give full details here. You must specify specific single days or half-days only. Block bookings will not be accepted.",
			'required'		=> false,
			'rows'			=> 3,
		));
		
		# E-mail the result to the webmaster as a backup record
		$form->setOutputEmail ($this->settings['recipient'], $this->settings['serverAdministrator'], $this->settings['applicationName'] . " for {$dateString} in the {$period}", NULL, $replyToField = 'email');
		
		# Confirm what they submitted on screen
		$form->setOutputScreen ();
		
		# Process the form and produce the results
		$form->process ($html);
		
		# Show the HTML
		echo $html;
	}
}

?>