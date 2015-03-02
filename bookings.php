<?php

# Class to provide a system for booking availability slots
require_once ('frontControllerApplication.php');
class bookings extends frontControllerApplication
{
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
			'csv' => NULL,
			'recipient'	=> NULL,	// Who to send the e-mail requests to
			'form' => true,
			'div' => 'bookings',
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
		require_once ('csv.php');
		require_once ('timedate.php');
		
		# Process the place titles
		if ($this->action != 'settings') {
			$placeTitles = explode ("\n", $this->settings['placeTitles']);
			foreach ($placeTitles as $index => $placeTitle) {
				$placeTitles[$index] = trim ($placeTitle);
			}
			$this->settings['placeTitles'] = $placeTitles;
		}
		
$this->settings['placeTitles'] = array (
	1 => 'Morning1',
	2 => 'Morning2',
	3 => 'Afternoon1',
	4 => 'Afternoon2',
);

		# Get the data or end
		if (!$this->data = $this->getData ()) {return false;}
		
		# Get the dates
		$this->dates = $this->getDates ();
		
	}
	
	
	# Function to get the dates for future months
	public function getDates ()
	{
		# Create an array of dates in future months
		$dates = timedate::getDatesForFutureMonths ($this->settings['listMonthsAhead'], 'Y-m-d', $removeWeekends = true);
		
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
		# Get the CSV data
		if (!$rawdata = csv::getData ($this->settings['csv'])) {
			echo $this->reportError ($adminMessage = "Data from {$this->settings['csv']} could not be read.\n\nPerhaps you need to do\nmount -a\nas root on the webserver.", $publicMessage = 'Error: There was a problem reading the database. The server administrator has been informed and will fix the problem as soon as possible.');
			return false;
		}
		
		# Convert the date
		foreach ($rawdata as $key => $value) {
			list ($day, $month, $year) = explode ('/', trim ($key), 3);
			$date = $year . $month . $day;
			$data[$date] = $value;
		}
		
		# Sort the data
		ksort ($data);
		
		# Return the data
		return $data;
	}
	
	
	# Function to show the listing
	public function home ()
	{
		# Start the HTML
		$html = '';
		
		# Introductory text
		$html .= $this->settings['introductoryTextHtml'];
		
		# Start the table of dates
		$html  .= "\n" . '<table class="lines sprilibrarybookings">';
		$html .= "\n\t<tr>";
		$html .= "\n\t\t<th class=\"date\">Date</th>";
		$html .= "\n\t\t<th>Desk 1<br />Morning</th>";
		$html .= "\n\t\t<th>Desk 2<br />Morning</th>";
		$html .= "\n\t\t<th>Desk 1<br />Afternoon</th>";
		$html .= "\n\t\t<th>Desk 2<br />Afternoon</th>";
		$html .= "\n\t</tr>";
		
		# Loop through the data
		foreach ($this->dates as $date) {
			
			# Prepare to look up what has been booked
			$key = str_replace ('-', '', $date);
			
			# Get the formatted date
			$dateFormatted = timedate::convertBackwardsDateToText ($date);
			
			# Add the date, signalling the start of the week when that occurs
			$html .= "\n\t<tr" . ((substr ($dateFormatted, 0, 6) == 'Monday') ? ' class="newweek"' : '') . '>';
			$html .= "\n\t\t<td class=\"date\">" . $dateFormatted . '</td>';
			
			# Add the bookings
			foreach ($this->settings['placeTitles'] as $fieldName) {
				
				# Determine whether the field is booked
				$isBooked = (isSet ($this->data[$key][$fieldName]) ? ($this->data[$key][$fieldName] ? true : false) : false);
				$isMorning = (($fieldName == 'Morning1') || ($fieldName == 'Morning2'));
				
				# Determine whether the institution is closed this day and override further processing if so
				if ($isClosed = ($isBooked && (strtolower (trim ($this->data[$key][$fieldName])) == 'closed'))) {
					$html .= "\n\t\t" . '<td colspan="4" class="closed">&mdash; Closed this day &mdash;</td>';
					break;
				}
				
				# Create the HTML
				$html .= "\n\t\t<td" . ($isBooked ? ' class="booked"' : '') . '>' . ($isBooked ? 'Booked' : "<a rel=\"nofollow\" href=\"{$this->baseUrl}/request/{$key}/" . ($isMorning ? 'morning' : 'afternoon') . '/">Available</a>') . '</td>';
			}
			
			# Finish the row
			$html .= "\n\t</tr>";
		}
		
		# Complete the HTML
		$html .= "\n" . '</table>';
		
		# Show the HTML
		echo $html;
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
		));
		
		# Determine the form fields
		$form->heading ('p', application::htmlUl (array ("<a href=\"{$this->baseUrl}/\">Back to list of dates</a>")));
		$form->heading ('', $this->settings['bookingPageTextHtml']);
		$form->heading (2, "Request a booking for: the <u>{$period}</u> of <u>{$dateString}</u>");
		
		$form->input (array (
			'name'			=> 'name',
			'title'					=> 'Your name',
			'required'				=> true,
		));
		$form->input (array (
			'name'			=> 'institution',
			'title'					=> 'Your institution',
			'description'	=> 'Please state the academic institution from which you are applying.',
			'required'				=> true,
		));
		$form->textarea (array (
			'name'			=> 'documents',
			'title'					=> 'Which documents do you wish to see?',
			'required'				=> true,
			'cols'				=> 40,
		));
		$form->email (array (
			'name'			=> 'email',
			'title'					=> 'E-mail address',
			'description'	=> 'Correspondence by e-mail is preferred where possible.',
			'required'				=> false,
		));
		$form->textarea (array (
			'name'			=> 'address',
			'title'					=> 'Your address',
			'required'				=> true,
			'cols'				=> 40,
			'rows'				=> 3,
		));
		$form->input (array (
			'name'			=> 'phone',
			'title'					=> 'Phone number',
			'required'				=> false,
		));
		$form->input (array (
			'name'			=> 'arrivaltime',
			'title'					=> 'Arrival time on first day',
			'required'				=> true,
		));
		$form->textarea (array (
			'name'			=> 'subsequentdays',
			'title'					=> 'Any subsequent (single/half-) days?',
			'description'	=> "If you need to stay for more than just the {$period} of {$dateString}, please give full details here. You must specify specific single days or half-days only. Block bookings will not be accepted.",
			'required'				=> false,
			'rows'				=> 3,
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