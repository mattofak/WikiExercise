<?php
/**
 * WikiExersize - Matt Walker 2012
 **/

class CurrencyConverter {
	/** 
	 * Convert's currencies into USD based on the day's market rate. Market
	 * rate is defined by 3rd party library query via XML. If the daily
	 * market rate is not yet defined, the class will query the webservice
	 * and obtain it if possible. Otherwise it will use the most recent valid
	 * data.
	 *
	 * --- Thoughts: yes this would be much better if I had one single MySQL
	 * instance per session, and probably if the rates were stored in
	 * a variable cache (memcached or similar). But not wanting to get too
	 * complex with a bunch of support classes... I present the following
	 * --- And yes, I know it's bad to put passwords in source files, but I
	 * didn't want to create a config file for this example.
	 **/

	const RATE_URI   = "http://toolserver.org/~kaldari/rates.xml";
	
	const MYSQL_URI  = "localhost";
	const MYSQL_DB   = "mmwalker_wiki";
	const MYSQL_USR  = "wiki_test";
	const MYSQL_PASS = "HighlySecurePassword";

	private $rates_gather_failed = False;	// And this really should be a global... So that we dont hammer the server
	private $mysql_conn = NULL;

	/** public methods ****************************************************/

	/**
	 * Sets up the initial connections to things...
	 **/
	function __construct() {
		$this->mysql_conn = mysqli_connect(self::MYSQL_URI, self::MYSQL_USR, self::MYSQL_PASS, self::MYSQL_DB);
		if (mysqli_connect_error()) {
			throw new Exception('Could not establish persistant link to the MySQL server. ' .  mysqli_connect_error());
		}
	}

	/**
	 * Clean up the mess
	 **/
	function __destruct() {
		$this->mysql_conn->close();
	}

	/**
	 * Convert a currency scalar (or array) to USD from an ISO currency. Expects strings in format 'ISO Value'
	 **/
	public function convert_currency($input) {
		$result = '';

		if (is_array($input)) {
			$ary_result = array();

			foreach ($input as $line) {
				$ary_result[] = $this->convert_currency($line);
			}

			$result = $ary_result;
		} else {
			$tdata = preg_split('/\s+/', $input);
			$rate = $this->obtain_valid_rate($tdata[0]);
			if ($rate == False) {
				// I guess return 0...
				$result = "USD 0.0";
			} else {
				$result = sprintf("USD %f", round(floatval($tdata[1]) * $rate, 2));
			}
		}

		return $result;
	}

	/** private methods **********************************************************/
	/**
	 * Load all the rates from the 3rd party server and return a dictionary of:
	 * ISO5 -> Rate
	 **/
	private function query_rate_server() {
		$rates = array();

		libxml_use_internal_errors(True);
		$xml = simplexml_load_file(self::RATE_URI);
		if ($xml == False) {
			// Log the exception using the appropriate logging mechanism or...
			throw new Exception('Could not load new rates from 3rd party server: '. libxml_get_errors());
		} else {
			// Hooray, we have data!
			foreach ($xml->conversion as $item) {
				$rates[trim($item->currency)] = floatval($item->rate);
			}
		}

		return $rates;
	}

	/**
	 * Store each rate gathered from the server into the MySQL database
	 **/
	private function store_rate_results($rates) {
		foreach ($rates as $iso => $rate) {
			// The following can actually throw an error, but typically only on duplicate values
			$this->mysql_conn->query(sprintf("INSERT INTO exchange_rates VALUES('%s', curdate(), '%f');",
								$this->mysql_conn->real_escape_string($iso), $rate));
		}
	}

	/**
	 * Obtain the most recent valid rate, or return false
	 **/
	private function obtain_valid_rate($iso_currency) {
		$iso_rate = False;

		// We could totally cache this call...
		$result = $this->mysql_conn->query(sprintf("SELECT curdate(), validasof, rate FROM exchange_rates WHERE currency='%s' ORDER BY validasof DESC LIMIT 1;",
								$this->mysql_conn->real_escape_string($iso_currency)));
		
		
		if (($result != False) && ($result->num_rows == 1)) {
			// Data was returned!
			$data = $result->fetch_row();
			if (($data[0] != $data[1]) && ($this->rates_gather_failed == False))
			{
				// Stale data! ARGHHH, get new data...
				$new_rates = $this->query_rate_server();
				$this->store_rate_results($new_rates);

				// Is our rate in this new data?
				if (array_key_exists($iso_currency, $new_rates)) {
					$iso_rate = $new_rates[$iso_currency];
				} else {
					// We shall have to use the old rate
					$iso_rate = $data[2];
				}
			} else {
				// Data in database is new enough to use
				$iso_rate = $data[2];
			}
		} else {
			// No data :( but can we get data?
			$new_rates = $this->query_rate_server();
			$this->store_rate_results($new_rates);

			if (array_key_exists($iso_currency, $new_rates)) {
				$iso_rate = $new_rates[$iso_currency];
			}
		}

		// If we're here, we either have a valid rate, or the rate is still false
		if (($iso_rate == False) && ($this->rates_gather_failed == False)) {
			$rates_gather_failed = time();
		} elseif (($iso_rate != False) && ($this->rates_gather_failed != False) && ($rates_gather_failed < (time() - 60))) {
			// Reset the fail flag, 60 seconds have passed and we have valid data!
			$this->rates_father_failed = False;
		}
		
		return $iso_rate;
	}
}
?>
