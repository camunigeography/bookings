<?php

#!# Add multi-day listing integration which fills in extra dates automatically (e.g. at <baseUrl>/requests/10/edit.html )
#!# Integrated e-mailing when editing records - would need sinenomine support
#!# Stats system needed to compile booking stats automatically



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
			'settingsTableExplodeTextarea' => true,
			'serverAdministrator'	=> NULL,	// E-mail address of the server administrator
			'form' => true,
			'div' => 'bookings',
			'formDiv' => 'lines ultimateform horizontalonly bookingform',
			'formValidationCallback' => false,
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
				'description' => 'Confirmed bookings - edit',
				'url' => 'edit.html',
				'tab' => 'Edit/view bookings',
				'administrator' => true,
				'icon' => 'pencil',
			),
			'request' => array (
				'description' => false,
				'url' => 'request/%1/%2/',
				'usetab' => 'home',
			),
			'requests' => array (
				'description' => false,
				'url' => 'requests/',
				'tab' => 'Requests',
				'administrator' => true,
				'icon' => 'application_double',
			),
			'export' => array (
				'description' => 'iCal feed - add listing to your calendar application',
				'url' => 'export.html',
				'administrator' => true,
				'parent' => 'admin',
				'subtab' => 'Export to calendar',
			),
			'ical' => array (
				'description' => false,
				'url' => 'bookings.ics',
				'authentication' => false,	// Not ideal, but othewise Google Calendar can't see it
				'export' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Username' PRIMARY KEY,
			  `editingStateRequests` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'State'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='System administrators';
			
			CREATE TABLE IF NOT EXISTS `bookings` (
			  `id` int(11) NOT NULL COMMENT 'Automatic key' AUTO_INCREMENT PRIMARY KEY,
			  `date` date NOT NULL COMMENT 'Date',
			  `place` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Place',
			  `slot` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Place slot',
			  `bookingFor` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Value',
			  `approved` int(1) DEFAULT NULL COMMENT 'Approved?'
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Bookings';
			
			/*
				This is an *example* requests table; the specific fields required will be dependent on the installation.
				The ones starting -- are examples you could include but not enabled by default; all others are mandatory.
				Any fields whose name starts with 'internal' will be shown only to internal users and not general users filling out the request form.
			*/
			CREATE TABLE IF NOT EXISTS `requests` (
			  `id` int(11) NOT NULL COMMENT 'Request no.' AUTO_INCREMENT PRIMARY KEY,
		--	  `visitType` enum('Museum visit','Museum workshop','Museum tour','Museum outreach') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Visit type',
		--	  `visitTypeOther` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '(Specific details)',
		--	  `institutionType` enum('','School','Language school','Further Education (sixth form)','Higher Education (degree-level)','Other') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Type of institution',
			  `bookingFor` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Name of group or school',
		--	  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Name of organiser',
		--	  `address` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Address',
		--	  `postcode` varchar(9) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Postcode',
		--	  `country` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Country',
		--	  `telephone` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Phone number',
			  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'E-mail',
		--	  `revisit` enum('','Yes','No') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Have you visited before?',
		--	  `heardof` enum('','Word of mouth','Visited before','Live locally','Newspaper','Other printed materials','Website','Twitter','Facebook','Other') COLLATE utf8_unicode_ci NOT NULL COMMENT 'How did you hear about us?',
			  `date` date NOT NULL COMMENT 'Requested date',
		--	  `alternativeDates` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Possible alternative dates',
			  `place` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Preferred timeslot',
		--	  `participants` int(2) UNSIGNED NOT NULL COMMENT 'Number of participants<br />(max 25 adults, or one class of children)',
		--	  `ageGroups` set('0-5 years old (Early years)','5-7 years old (UK: KS1)','7-11 years old (UK: KS2)','11-14 years old (UK: KS3)','14-18 years old','Higher Education','Adult') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Age group(s)',
		--	  `adults` int(2) UNSIGNED DEFAULT NULL COMMENT 'Number of accompanying adults (for groups of children under the age of 18)',
		--	  `specialNeeds` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Any special needs?',
		--	  `comments` text COLLATE utf8_unicode_ci COMMENT 'Any other information/comments/requests',
			  `approved` enum('Unreviewed','Approved','Rejected','Cancelled','No-show') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Unreviewed' COMMENT 'Approved?',
		--	  `internalVisitContent` set('Some package','Another work package','Third option','Other') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Visit content',
		--	  `internalVisitContentOther` text COLLATE utf8_unicode_ci COMMENT 'Visit content (detail for other)',
		--	  `internalPhoneCallLog` text COLLATE utf8_unicode_ci COMMENT 'Phone call log',
			  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Automatic timestamp'
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of requests';
			
			CREATE TABLE IF NOT EXISTS `settings` (
			  `id` int(11) NOT NULL COMMENT 'Automatic key (ignored)' AUTO_INCREMENT PRIMARY KEY,
			  `recipient` VARCHAR(255) NOT NULL COMMENT 'E-mail recipient',
			  `listMonthsAheadPublic` int(2) NOT NULL DEFAULT '3' COMMENT 'How many months ahead to list (public)',
			  `listMonthsAheadPrivate` int(2) NOT NULL DEFAULT '12' COMMENT 'How many months ahead to list (private)',
			  `period` ENUM('days','weeks') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'days' COMMENT 'Booking period',
			  `excludeNextPeriod` int(2) NOT NULL DEFAULT '5' COMMENT 'How many days/weeks from today should not be listed publicly',
			  `weekdays` set('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Show which days?',
			  `places` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Place title URL monikers',
			  `placeLabels` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Place labels',
			  `placeLabelsAbbreviated` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Place labels (abbreviated)',
			  `placeSlots` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Slots per place title',
			  `placeTimePeriods` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Place time periods (as comma-separated pairs), used for iCal calendar feed',
			  `icalMonthsBack` INT(11) NULL DEFAULT NULL COMMENT 'How many months back should the iCal feed start from? (Leave blank to show everything.)',
			  `introductoryTextHtml` text COLLATE utf8_unicode_ci COMMENT 'Introductory text',
			  `bookingPageTextHtml` text COLLATE utf8_unicode_ci COMMENT 'Booking page introductory text',
			  `agreementText` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Agreement tickbox text at end (if any)',
			  `awayMessage` VARCHAR(255) NULL COMMENT 'Away message'
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Settings';
		";
	}
	
	
	# Additional processing
	public function main ()
	{
		# Load required libraries
		require_once ('timedate.php');
		
		# Compile the place settings into an array
		$this->places = array ();
		foreach ($this->settings['places'] as $index => $moniker) {
			list ($startTime, $untilTime) = explode (',', $this->settings['placeTimePeriods'][$index], 2);
			$this->places[$moniker] = array (
				'label'						=> $this->settings['placeLabels'][$index],
				'labelAbbreviated'			=> $this->settings['placeLabelsAbbreviated'][$index],
				'labelAbbreviatedLowercase'	=> strtolower ($this->settings['placeLabelsAbbreviated'][$index]),
				'slots'						=> $this->settings['placeSlots'][$index],
				'startTime'					=> $startTime,
				'untilTime'					=> $untilTime,
			);
		}
		
		# Ensure a settings entry is present
		#!# Should be converted to generic handling in frontControllerApplication
		if (!isSet ($this->settings['listMonthsAheadPublic'])) {	// Any settings table setting would do
			if ($this->userIsAdministrator) {
				if ($this->action != 'settings') {
					echo "\n<p>Please enter the <a href=\"{$this->baseUrl}/settings.html\">settings</a> for this installation.</p>";
					return false;
				}
			} else {
				echo "\n<p>This system is not yet set up. The administrator needs to log in and add settings. Please check back later.</p>";
				return false;
			}
		}
	}
	
	
	# Settings
	public function settings ($dataBindingSettingsOverrides = array ())
	{
		# Define overrides
		$dataBindingSettingsOverrides = array (
			'attributes' => array (
				'recipient'				=> array ('heading' => array (3 => 'Notifications')),
				'listMonthsAheadPublic'	=> array ('heading' => array (3 => 'Listings of bookable places')),
				'places'				=> array ('heading' => array (3 => 'Places')),
				'icalMonthsBack'		=> array ('heading' => array (3 => 'Calendar feed')),
				'introductoryTextHtml'	=> array ('heading' => array (3 => 'Notice/message texts')),
			),
		);
		
		# Run the main settings system with the overriden attributes
		return parent::settings ($dataBindingSettingsOverrides);
	}
	
	
	# Settings additional processing
	public function settingsGetUnfinalised (&$form)
	{
		# Add getUnfinalised processing
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			
			# Check that the number of input widgets in the eventual editing page doesn't exceed the PHP max_input_vars setting
			$submittedPlaceTitles = explode ("\n", trim ($this->settings['placeTitles']));
			$widgetsRequired = ($unfinalisedData['listMonthsAheadPrivate'] * 31 * count ($submittedPlaceTitles));
			if ($widgetsRequired > ini_get ('max_input_vars')) {
				$form->registerProblem ('toomany', 'The number of months is too high (too many input boxes would have to be created, beyond what the server can handle). Please reduce the number.', 'listMonthsAheadPrivate');
			}
			
			# Ensure 'weeks' period has only one day, representing the first day of the week
			if ($unfinalisedData['period'] == 'weeks') {
				$totalWeekdays = 0;
				foreach ($unfinalisedData['weekdays'] as $weekday) {
					if ($weekday) {
						$totalWeekdays++;
					}
				}
				if ($totalWeekdays != 1) {
					$form->registerProblem ('weekdaysInvalid', 'If setting the period to weeks, you must specify exactly one weekday, representing the day of the week the booking starts on.', 'weekdays');
				}
			}
		}
	}
	
	
	# Function to get the dates for future months; enable-all-days mode removes various restrictions
	public function getDates ($fromToday = false, $enableAllDays = false)
	{
		# Never enable all dates when in weeks mode
		if ($this->settings['period'] == 'weeks') {
			$enableAllDays = false;
		}
		
		# Determine the days to show
		$weekdays = ($this->settings['weekdays'] ? explode (',', strtolower ($this->settings['weekdays'])) : true);
		if ($enableAllDays) {
			$weekdays = true;
		}
		
		# Create an array of dates in future months
		$dates = timedate::getDatesForFutureMonths ($this->settings['listMonthsAheadPublic'], 'Y-m-d', $weekdays);
		
		# If the user is an admin, show the fuller list, and determine the first date that is private
		if ($this->userIsAdministrator) {
			$datesPublic = $dates;
			$dates = timedate::getDatesForFutureMonths ($this->settings['listMonthsAheadPrivate'], 'Y-m-d', $weekdays);
			$privateDates = array_diff ($dates, $datesPublic);
			$this->firstPrivateDate = application::array_first_value ($privateDates);
		}
		
		# Remove earliest dates in the public list
		if (!$fromToday) {
			for ($i = 0; $i < $this->settings['excludeNextPeriod']; $i++) {
				array_shift ($dates);
			}
		}
		
		# Return the dates
		return $dates;
	}
	
	
	# Function to get the data
	private function getBookedSlotsData ($dates)
	{
		# Determine the first and last dates, so that only this range is obtained for efficiency
		$firstDate = $dates[0];
		$datesValues = array_values ($dates);	// This temp has to be used to avoid "Strict Standards: Only variables should be passed by reference"
		$untilDate = end ($datesValues);
		
		# Get any data for between these ranges
		$query = "SELECT
				id,date,place,slot,bookingFor,approved,1 AS reviewed,'manual' AS type
			FROM {$this->settings['database']}.{$this->settings['table']}
			WHERE
				    `date` >= '{$firstDate}'
				AND `date` <= '{$untilDate}'
			ORDER BY `date`, place, slot
		;";
		$rawDataManual = $this->databaseConnection->getData ($query);
		
		# Add in actual bookings; slot '_' represents auto-allocation
		$query = "SELECT
				id,date,place,'_' AS slot,bookingFor,IF(approved='Approved',1,'') AS approved,IF(approved='Unreviewed','',1) AS reviewed,'request' AS type
			FROM {$this->settings['database']}.requests
			WHERE
				    `date` >= '{$firstDate}'
				AND `date` <= '{$untilDate}'
			ORDER BY `date`, place
		;";
		$rawDataRequests = $this->databaseConnection->getData ($query);
		$rawDataRequests = $this->databaseConnection->splitSetToMultipleRecords ($rawDataRequests, 'place');
		
		# Merge
		$bookings = array_merge ($rawDataManual, $rawDataRequests);
		
		# Regroup
		$data = array ();
		foreach ($bookings as $booking) {
			$date = $booking['date'];
			$place = $booking['place'];
			$slot = $booking['slot'];
			$approvalStatus = ($booking['approved'] ? 'approved' : 'unapproved');
			$data[$date][$place][$approvalStatus][$slot][] = $booking;
		}
		
		# Assign auto-allocated slots
		foreach ($data as $date => $bookingsByDate) {
			foreach ($bookingsByDate as $place => $bookingsByPlace) {
				foreach ($bookingsByPlace as $approvalStatus => $bookingsByApprovalStatus) {
					
					# Determine if there are any slots to be auto-allocated
					if (isSet ($bookingsByApprovalStatus['_'])) {
						
						# Determine the free slot numbers; e.g. if four slots are defined for this type of place, and 1 is taken, the result would be array(0,2,3)
						$availableSlots = array ();
						for ($slot = 0; $slot < $this->places[$place]['slots']; $slot++) {
							if (!isSet ($bookingsByApprovalStatus[$slot])) {
								$availableSlots[] = $slot;
							}
						}
						
						# Loop through each booking needing to be auto-allocated, shifting it from _ to the real slot number
						foreach ($bookingsByApprovalStatus['_'] as $index => $booking) {
							if ($availableSlots) {
								$nextAvailableSlot = array_shift ($availableSlots);		// i.e. take the first available slot from the stack of available slots
								$data[$date][$place][$approvalStatus][$nextAvailableSlot][] = $booking;
								unset ($data[$date][$place][$approvalStatus]['_'][$index]);
							}
						}
						
						# Remove empty auto-allocation containers
						if (empty ($data[$date][$place][$approvalStatus]['_'])) {
							unset ($data[$date][$place][$approvalStatus]['_']);
						}
					}
				}
			}
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
	
	
	# Mass-editing page
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
				'size' => 22,
			));
		}
		if ($result = $form->process ($html)) {
			
			# Determine the changed fields, for efficiency
			$changedFields = application::array_changed_values_fields ($formElements, $result);
			if ($changedFields) {
				
				# Insert/update the changes
				foreach ($changedFields as $field) {
					
					# Determine the match for existing data, and set what the new record should become
					list ($date, $place, $slot) = explode ('_', $field, 3);
					$where = array (
						'date' => $date,
						'place' => $place,
						'slot' => $slot,
						'approved' => '1',
					);
					$data = $where;	// Clone
					$data['bookingFor'] = $result[$field];
					
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
				$confirmationHtml = "\n<div class=\"graybox\">\n\t<p class=\"success\"><img src=\"/images/icons/tick.png\" alt=\"Tick\" class=\"icon\" /> <strong>The " . (count ($changedFields) == 1 ? 'change has' : 'changes have') . " now been made, as shown below. <a href=\"{$this->baseUrl}/\">Return to the public listing</a></strong> or edit further below.</p>\n</div>";
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
		# Get the dates; in edit mode, reload the dates, but from the current date (rather than a week ahead)
		if ($editMode) {
			$dates = $this->getDates (true, $editMode);
		} else {
			$dates = $this->getDates ();
		}
		
		# Get the booked slots data (which may be empty)
		$bookedSlotsData = $this->getBookedSlotsData ($dates);
		
		# Determine the first day of the week in the settings
		$weekdays = explode (',', strtolower ($this->settings['weekdays']));
		$firstDayOfWeekInSettings = $weekdays[0];
		
		# Assemble the data for a table, looping through the dates, so that all are shown, irrespective of whether a booking is present
		$table = array ();
		foreach ($dates as $date) {
			
			# Set the key for this row, which will be used as the class for this row
			$key = 'week-' . $date;
			
			# Determine if this is Monday
			$isFirstDayOfWeek = strtolower (date ('l', strtotime ($date))) == $firstDayOfWeekInSettings;
			if ($isFirstDayOfWeek) {$key .= ' newweek';}
			
			# If this is the first private date, add an extra class
			if ($date == $this->firstPrivateDate) {
				$table['firstprivatedate'] = array ('date' => 'Dates from here are not yet public');
				foreach ($this->places as $place => $placeAttributes) {
					for ($slot = 0; $slot < $placeAttributes['slots']; $slot++) {
						$column = $place . '_' . $slot;
						$table['firstprivatedate'][$column] = '';
					}
				}
			}
			
			# Get the formatted date and set this as the first column
			$table[$key]['date'] = date ('l, jS F Y', strtotime ($date));
			if ($this->settings['period'] == 'weeks') {
				$table[$key]['date'] = 'Week beginning <br />' . $table[$key]['date'];
			}
			
			# Determine whether the institution is closed this day
			$isClosedToday = $this->isClosedToday ($bookedSlotsData, $date);
			
			# Determine the data for each place
			foreach ($this->places as $place => $placeAttributes) {
				for ($slot = 0; $slot < $placeAttributes['slots']; $slot++) {
					$column = $place . '_' . $slot;
					
					# If closed, state this
					if ($isClosedToday && !$editMode) {
						$table[$key][$column] = '<span class="booked" title="closed">Closed this day</span>';
						continue;	// Next slot/place
					}
					
					# Is there a booking in this slot/place?
					$isBooked = (isSet ($bookedSlotsData[$date]) && isSet ($bookedSlotsData[$date][$place]) && isSet ($bookedSlotsData[$date][$place]['approved']) && isSet ($bookedSlotsData[$date][$place]['approved'][$slot]) && isSet ($bookedSlotsData[$date][$place]['approved'][$slot][0]));
					if ($isBooked) {
						$booking = ($isBooked ? $bookedSlotsData[$date][$place]['approved'][$slot][0] : false);
						
						# Set the default cell value
						$table[$key][$column] = '<span class="booked" title="' . htmlspecialchars ($booking['bookingFor']) . '">Booked</span>';
						
						# If the user is edit mode (and therefore an Administrator), instead give more details
						if ($editMode) {
							if ($booking['type'] == 'request') {	// Applied-for bookings
								$linkUrl = "{$this->baseUrl}/requests/{$booking['id']}/edit.html";
								$editLinkStart = "<a rel=\"nofollow\" href=\"{$linkUrl}\">";
								$table[$key][$column] = '<span class="booked">Booked: ' . $editLinkStart . htmlspecialchars ($booking['bookingFor']) . '</a>' . '</span>';
							} else {	// Manually-added slots
								$fieldname = $date . '_' . $column;
								$formElements[$fieldname] = $booking['bookingFor'];
								$table[$key][$column] = '{' . $fieldname . '}' . $this->unreviewedEntries ($bookedSlotsData, $date, $place, $slot);
							}
						}
						
						continue;	// Next slot/place
					}
					
					# Determine a link to add a request
					$dateSlug = str_replace ('-', '', $date);
					$linkUrl = "{$this->baseUrl}/request/{$dateSlug}/{$place}/";
					
					# The slot is therefore available; in edit mode, create a widget
					if ($editMode) {
						$fieldname = $date . '_' . $column;
						$formElements[$fieldname] = '';
						$table[$key][$column] = '{' . $fieldname . '}' . "<a class=\"add\" href=\"{$linkUrl}\">+</a>" . $this->unreviewedEntries ($bookedSlotsData, $date, $place, $slot);
						continue;	// Next slot/place
					}
					
					# For available slots in view mode, create a link
					$table[$key][$column] = "<a rel=\"nofollow\" href=\"{$linkUrl}\">Available</a>";
				}
			}
		}
		
		# Determine the headings
		$placeTitles = array ();
		foreach ($this->places as $place => $placeAttributes) {
			for ($slot = 0; $slot < $placeAttributes['slots']; $slot++) {
				$column = $place . '_' . $slot;
				$placeTitles[$column] = $placeAttributes['labelAbbreviated'] . ($placeAttributes['slots'] == 1 ? '' : ' (slot&nbsp;' . ($slot + 1) . ')');	// Show slots starting with 1, though they are stored as zero-indexed (0,1,...)
			}
		}
		
		# Compile as HTML
		$html = application::htmlTable ($table, $placeTitles, 'lines bookingslist', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true, $showColons = true, false, $addRowKeyClasses = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine if the institution is closed today
	public function isClosedToday ($bookings, $date)
	{
		# End if nothing this day
		if (!isSet ($bookings[$date])) {return false;}
		
		# Traverse down the structure of bookings for this date
		foreach ($bookings[$date] as $place => $bookingsByPlace) {
			if (isSet ($bookingsByPlace['approved'])) {
				foreach ($bookingsByPlace['approved'] as $slot => $bookingsBySlot) {
					foreach ($bookingsBySlot as $index => $booking) {	// Should only be one, i.e. [0]
						if ($booking['bookingFor'] == 'closed') {
							return true;
						}
					}
				}
			}
		}
		
		# No match - not closed
		return false;
	}
	
	
	# Function to determine if there are unreviewed entries for a specified date/place/slot
	public function unreviewedEntries ($bookings, $date, $place, $slot)
	{
		# Create an array of entries
		$unreviewedEntries = array ();
		if (isSet ($bookings[$date]) && isSet ($bookings[$date][$place]) && isSet ($bookings[$date][$place]['unapproved']) && isSet ($bookings[$date][$place]['unapproved'][$slot])) {
			foreach ($bookings[$date][$place]['unapproved'][$slot] as $index => $booking) {
				if (!$booking['reviewed']) {
					$unreviewedEntries[] = "[<a href=\"{$this->baseUrl}/requests/{$booking['id']}/edit.html\">#{$booking['id']}</a>]";
				}
			}
		}
		
		# Return false if none
		if (!$unreviewedEntries) {return false;}
		
		# Compile the HTML
		$html = '<span class="small comment"><br />Unreviewed: ' . implode (' ', $unreviewedEntries) . '</span>';
		
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
			echo "\n<p class=\"warning\">You didn't specify a date.</p>";
			return false;
		}
		
		# Get the dates; admins can access all dates
		if ($this->userIsAdministrator) {
			$dates = $this->getDates (true, true);
		} else {
			$dates = $this->getDates ();
		}
		
		# Ensure it is a valid date by adding the hyphens in then checking it is in the generated list of dates
		list ($year, $month, $day) = sscanf ($_GET['date'], '%4s%2s%2s');
		$date = "{$year}-{$month}-{$day}";
		if (!in_array ($date, $dates)) {
			echo "\n<p class=\"warning\">The date you selected is not valid. Please check the URL and try again.</p>";
			return false;
		}
		
		# Ensure a place is specified
		if (!isSet ($_GET['place'])) {
			echo "\n<p class=\"warning\">You didn't specify a place.</p>";
			return false;
		}
		
		# Ensure the specified place is valid
		if (!array_key_exists ($_GET['place'], $this->places)) {
			echo "\n<p class=\"warning\">The place you selected is not valid. Please check the URL and try again.</p>";
			return false;
		}
		$place = $_GET['place'];
		
		# Get the booked slots data (which may be empty)
		$bookedSlotsData = $this->getBookedSlotsData ($dates);
		
		# Ensure there are places available for the specified date and place
		if (!$this->placesAvailable ($dates, $bookedSlotsData, $date, $place, $errorMessageHtml)) {
			echo "\n{$errorMessageHtml}";
			return false;
		}
		
		# Start the HTML
		$html .= "\n<h2>Request a booking for: <u>" . htmlspecialchars ($this->places[$place]['labelAbbreviatedLowercase']) . '</u> on <u>' . timedate::convertBackwardsDateToText ($date) . '</u>' . '</h2>';
		
		# Determine the e-mail introductory text, which will include the link to the record about to be written; sending the e-mail manually just after the database write is very messy
		$currentHighestIdQuery = "SELECT MAX(id) AS currentHighestId FROM {$this->settings['database']}.requests;";
		$currentHighestId = $this->databaseConnection->getOneField ($currentHighestIdQuery, 'currentHighestId');
		$predictedId = $currentHighestId + 1;
		#!# Need to make this configurable in the settings
		// $emailIntroductoryText  = "Thank you for your booking request. Please await confirmation before proceeding with your visit.";
		$emailIntroductoryText  = 'Thank you for your booking request. Please await confirmation from our staff.';
		$emailIntroductoryText .= "\n\n\n" . str_repeat ('*', 76);
		$emailIntroductoryText .= "\n\nYou should review this online at:";
		$emailIntroductoryText .= "\n\n" . $_SERVER['_SITE_URL'] . $this->baseUrl . '/requests/' . $predictedId . '/edit.html';
		$emailIntroductoryText .= "\n\n" . str_repeat ('*', 76);
		$emailIntroductoryText .= "\n\n\n";
		
		# Start a form
		$form = new form (array (
			'displayRestrictions' => false,
			'databaseConnection' => $this->databaseConnection,
			'formCompleteText' => false,
			'antispam' => true,
			'div' => $this->settings['formDiv'],
			'autofocus' => true,
			'cols' => 40,
			'nullText' => false,
			'emailIntroductoryText' => $emailIntroductoryText,
			'unsavedDataProtection' => true,
			'picker' => true,
		));
		
		# Determine the form fields
		$form->heading ('p', application::htmlUl (array ("<a href=\"{$this->baseUrl}/\">Back to list of dates</a>")));
		if ($this->settings['bookingPageTextHtml']) {
			$form->heading ('', $this->settings['bookingPageTextHtml']);
		}
		
		# Set internal fields to be excluded
		$exclude = $this->databaseConnection->getFieldNames ($this->settings['database'], 'requests', false, $matchingRegexpNoForwardSlashes = '^internal.+');
		if (!$this->userIsAdministrator) {
			$exclude[] = 'approved';
		}
		
		# Databind the form
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => 'requests',
			'intelligence' => true,
			'attributes' => $this->formDataBindingAttributes ($dates),
			'exclude' => $exclude,
			'data' => array ('date' => $date, 'place' => $place, ),
		));
		
		# Add constraints
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			
			# If a form validation callback function is defined, run this callback to enable additional form constraints to be checked
			if ($this->settings['formValidationCallback']) {
				if (is_callable ($this->settings['formValidationCallback'])) {
					$callbackFunction = $this->settings['formValidationCallback'];
					if (!$result = $callbackFunction ($unfinalisedData, $errorMessage, $highlightField)) {
						$form->registerProblem ('error', $errorMessage, $highlightField);
					}
				}
			}
			
			# Check the date(s) requested are valid
			if ($unfinalisedData['date']) {
				$requestedPlaces = array ();
				foreach ($unfinalisedData['place'] as $placeId => $requested) {
					if ($requested) {
						if (!$this->placesAvailable ($dates, $bookedSlotsData, $unfinalisedData['date'], $placeId, $errorMessageHtml)) {
							$form->registerProblem ("placesunavailable{$placeId}", $errorMessageHtml);
						}
					}
				}
			}
		}
		
		# Data protection statement
		$form->heading (2, 'Agreement');
		$form->heading ('p', 'By submitting this form you are agreeing to this information being stored in our records.');
		$form->heading ('p', 'Information you submit here will be used only in accordance with the <a href="https://www.information-compliance.admin.cam.ac.uk/data-protection/general-data" target="_blank" title="[Link opens in a new window]">University\'s privacy and data protection policy</a>.');
		if ($this->settings['agreementText']) {
			$form->checkboxes (array (
				'name'		=> 'agreement',
				'title'		=> 'Confirmation',
				'values'	=> array ($this->settings['agreementText']),
				'required'	=> 1,
				'discard'	=> true,
			));
		}
		
		# E-mail the result; if there is a visitType, show this in the subject line
		$subject = $this->settings['applicationName'] . ' for ' . timedate::convertBackwardsDateToText (($unfinalisedData ? $unfinalisedData['date'] : $date)) . ": {$this->places[$place]['labelAbbreviatedLowercase']}";
		$formSpecification = $form->getSpecification ();
		if (isSet ($formSpecification['visitType'])) {
			$subject = "{name} - {visitType|compiled}";
		}
		$form->setOutputEmail ($this->settings['recipient'], $this->settings['serverAdministrator'], $subject, NULL, $replyToField = 'email');
		
		# Confirm what they submitted on screen
		// $form->setOutputScreen ();
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Save to the database
			if (!$this->databaseConnection->insert ($this->settings['database'], 'requests', $result)) {
				#!# The e-mail is still sent though
				$html = "<p>Apologies, an error occured when saving your submission. Please contact the Webmaster.</p>";
				if ($this->userIsAdministrator) {
					application::dumpData ($this->databaseConnection->error ());
				}
				echo $html;
				return false;
			}
			
			# Confirm success, wiping out any previously-generated HTML
			$html  = "\n<h2>Request a booking</h2>";
			$html .= "\n<div class=\"graybox\">";
			$html .= "\n\t<p><strong>Thank you. Your request has been sent.</strong> We will get back to you shortly to confirm whether the slot you requested is available, and to make any further arrangements.</p>";
			if ($this->settings['awayMessage']) {
				$html .= "\n\t<p class=\"warning\">" . htmlspecialchars ($this->settings['awayMessage']) . '</p>';
			}
			$html .= "\n</div>";
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to determine if places are available
	private function placesAvailable ($dates, $bookedSlotsData, $date, $place, &$errorMessageHtml)
	{
		# Define an error message
		$errorMessageHtml = 'Sorry, there are no slots available for the ' . htmlspecialchars ($this->places[$place]['labelAbbreviatedLowercase']) . ' of ' . timedate::convertBackwardsDateToText ($date) . '.';
		
		# Not available if not a valid date
		if (!in_array ($date, $dates)) {return false;}
		
		# Available if there are no current booked slots for this date
		if (!isSet ($bookedSlotsData[$date])) {return true;}
		
		# Available if there are no current booked slots for this date and place
		if (!isSet ($bookedSlotsData[$date][$place])) {return true;}
		
		# Available if there are no current booked slots for this date and place that are approved
		if (!isSet ($bookedSlotsData[$date][$place]['approved'])) {return true;}
		
		# Check the number of slots available
		$slotsMaximum = $this->places[$place]['slots'];
		$slotsTaken = count ($bookedSlotsData[$date][$place]['approved']);
		
		# Return whether there are slots free
		return ($slotsTaken < $slotsMaximum);
	}
	
	
	# Data binding attributes
	private function formDataBindingAttributes ($dates)
	{
		# Start an array of attributes
		$attributes = array ();
		
		# Prepare the places values
		$places = array ();
		foreach ($this->places as $placeMoniker => $placeAttributes) {
			$places[$placeMoniker] = $placeAttributes['label'];
		}
		$attributes['place'] = array (
			'type' => 'checkboxes',
			'values' => $places,
			'output' => array ('processing' => 'special-setdatatype'),
			'defaultPresplit' => true,
			'separator' => ',', /* #!# Ideally wouldn't be required - see note in ultimateForm re defaultPresplit */
		);
		
		# If the booking period is in weeks, convert the requested date and alternative date to week drop-down lists
		$attributes['date'] = array ();
		if ($this->settings['period'] == 'weeks') {
			$datesFormatted = array ();
			foreach ($dates as $date) {
				$datesFormatted[$date] = 'W/c ' . date ('D jS F Y', strtotime ($date));
			}
			$attributes['date'] = array (
				'type' => 'select',
				'title' => 'Requested week (week commencing)',
				'values' => $datesFormatted,
			);
			$attributes['alternativeDates'] = array (
				'type' => 'select',
				'title' => 'Alternative week (week commencing)',
				'values' => $datesFormatted,
			);
		}
		
		# Attributes (which may or may not be present, depending on table structure)
		$attributes['subsequentdays'] = array (
			'description'	=> 'If you need to stay for more than just the date and slot shown, please give full details here. You must specify specific single days or half-days only. Block bookings will not be accepted.',
		);
		$attributes['visitType'] = array (
			'heading' => array (3 => 'Type of visit'),
			'type' => 'radiobuttons',
			'values' => array (
				'Polar Museum visit'	=> 'Museum visit - self-guided (run entirely by the teacher or group leader)',
				'Polar Museum workshop'	=> 'Museum workshop (run by a member of museum staff); Thursdays/Fridays only',
				'Polar Museum tour'		=> 'Museum tour (led by a member of museum staff) - costs £80',
				'Polar Museum'			=> 'Other (please give details)',
			)
		);
		$attributes['institutionType'] = array (
			'heading' => array (3 => 'Group details'),
		);
		$attributes['date']['heading'] = array (3 => 'Booking request');
		$attributes['country'] = array (
			'type' => 'select',
			'values' => form::getCountries ($additionalStart = array ('United Kingdom', 'Group of visitors from multiple countries') ),
		);
		$attributes['approved'] = array (
			'heading' => array (3 => 'Internal notes'),
			'type' => 'radiobuttons',
		);
		$attributes['internalPhoneCallLog'] = array (
			'rows' => 10,
		);
		#!# These two force the input widget to be numeric, pending ultimateForm change (which merely uses enforceNumeric)
		$attributes['participants'] = array (
			'type' => 'number',
			'min' => 0,
		);
		$attributes['adults'] = array (
			'type' => 'number',
			'min' => 0,
		);
		
		# Return the array
		return $attributes;
	}
	
	
	# Admin editing section, substantially delegated to the sinenomine editing component
	public function requests ()
	{
		# Get the dates; admins can access all dates
		$dates = $this->getDates (true, true);
		
		# Get the databinding attributes
		$dataBindingAttributes = $this->formDataBindingAttributes ($dates);
		
		# Define extra settings
		$sinenomineExtraSettings = array (
			'formDiv' => $this->settings['formDiv'],
			'successfulRecordRedirect' => true,
			'headingLevel' => 2,
			'int1ToCheckbox' => true,
			'datePicker' => true,
		);
		
		# Delegate to the standard function for editing
		echo $this->editingTable (__FUNCTION__, $dataBindingAttributes, 'ultimateform bookingform', $this->action, $sinenomineExtraSettings);
	}
	
	
	# Export page
	public function export ()
	{
		# Define the location of the ICS file
		$icsFile = $this->baseUrl . '/' . $this->actions['ical']['url'];
		
		# Delegate to iCal class
		require_once ('ical.php');
		$ical = new ical ();
		$html = $ical->instructionsLink ($icsFile);
		
		# Show the HTML
		echo $html;
	}
	
	
	# iCal export
	public function ical ()
	{
		# Get the bookings data
		$query = "SELECT
			*
			FROM {$this->settings['database']}.requests
			WHERE
				approved = 'Approved'
				" . (strlen ($this->settings['icalMonthsBack']) ? " AND `date` >= DATE_SUB(NOW(), INTERVAL {$this->settings['icalMonthsBack']} MONTH) " : '') . "
			ORDER BY `date`,place
		;";
		$bookings = $this->databaseConnection->getData ($query, "{$this->settings['database']}.requests");
		
		# Split records with multiple place slots (e.g. 'morning,afternoon') into multiple records
		$bookings = $this->databaseConnection->splitSetToMultipleRecords ($bookings, 'place');
		
		# Compile the data
		$literalNewline = '\n';	// ical needs to see \n as text, not newlines; see http://stackoverflow.com/questions/666929/encoding-newlines-in-ical-files
		$events = array ();
		foreach ($bookings as $id => $booking) {
			$events[$id] = array (
				'title' => "{$booking['visitType']}: {$booking['bookingFor']}",
				'startTime' => strtotime ($booking['date'] . ' ' . $this->places[$booking['place']]['startTime']),
				'untilTime' => strtotime ($booking['date'] . ' ' . $this->places[$booking['place']]['untilTime']),
				'location' => $booking['visitType'],
				'description' =>
					"Name of organiser: {$booking['name']}" . $literalNewline .
					"Phone number: {$booking['telephone']}" . $literalNewline .
					"E-mail: {$booking['email']}" . $literalNewline .
					$literalNewline .
					"Number of participants: {$booking['participants']}" . $literalNewline .
					"Age group(s): " . $literalNewline . ' - ' . str_replace (',', $literalNewline . ' - ', $booking['ageGroups']) . $literalNewline .
					"Any other information/comments/requests: " . ($booking['comments'] ? $literalNewline . $booking['comments'] : '-') . $literalNewline .
					$literalNewline .
					"Visit content: " . ($booking['internalVisitContent'] ? $literalNewline . str_replace (',', ', ', $booking['internalVisitContent']) : '-') . $literalNewline .
					"Visit detail: " . ($booking['internalVisitContentOther'] ? $literalNewline . $booking['internalVisitContentOther'] : '-') . $literalNewline .
					$literalNewline .
					"Edit this booking at:{$literalNewline}{$_SERVER['_SITE_URL']}{$this->baseUrl}/{$this->actions['requests']['url']}{$booking['id']}/edit.html",		// $booking['id'] is used rather than $id which is fictional due to split bookings
			);
		}
		
		# Delegate to iCal class
		require_once ('ical.php');
		$ical = new ical ();
		echo $ical->create ($events, application::pluralise ($this->settings['applicationName']), 'University of Cambridge - Departmental code', $this->settings['applicationName']);
	}
}

?>
