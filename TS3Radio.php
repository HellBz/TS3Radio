<?php

//	Require authentication password
require_once("auth.php");

class TS3Radio
{
	protected $_clientHost;

	protected $_clientPort;

	protected $_radioUID;

	/**
	 * Socket.
	 *
	 * @var resource
	 */
	protected $_conn;

	/**
	 * Constructor.
	 *
	 * @param string $clientHost Hostname to connect to.
	 * @param int    $clientPort Port to connect to.
	 * @param string $radioUID   The radio UID. I don't know what that is.
	 */
	public function __construct($clientHost, $clientPort, $radioUID)
	{
		$this->_clientHost = $clientHost;
		$this->_clientPort = $clientPort;
		$this->_radioUID = $radioUID;
	}

	/**
	 * This would obviously be named better, but I don't really know what it's doing.
	 *
	 * @return void
	 */
	public function doStuff()
	{
		$this->_conn = fsockopen($this->_clientHost, $this->_clientPort, $errno, $errstr, 10);

		if (! $this->_conn) {
			throw new Exception('Failed to connect.');
		}
	}

	/**
	 * Get a line of data from the socket, and output it.
	 *
	 * @return string
	 */
	protected function _getLine()
	{
		//	Strip newlines and carriage returns
		$line = str_replace(array("\r", "\n"), '', fgets($this->_conn));
		echo $line, PHP_EOL;
		return $line;
	}

	/**
	 * Check that the last message we sent was successful.
	 *
	 * @return void
	 */
	protected function _checkSuccessful()
	{
		$temp = $this->_getLine();

		//	If not correct response to signify succesful command, close connection and kill PHP quoting error.
		if ($temp != "error id=0 msg=ok") {
			execCommand('quit');
			fclose($this->_conn);
			die('Unexpected TS3 error: \'' . $temp . '\'\n');
		}
	}

	/**
	 * Send a message.
	 *
	 * @param string $message Message to send.
	 *
	 * @return void
	 */
	protected function _sendMessage($message)
	{
		static $find = array(' ','&','\'');
		static $replace = array('\\s','\\&','\\\'');

		execCommand("sendtextmessage targetmode=2 msg=" . str_replace($find, $replace, $message));
	}

	/**
	 * Execute a command by sending it to the socket.
	 *
	 * @param string $command         Command to send.
	 * @param bool   $checkSuccessful Automatically check the command was successful after it's sent.
	 *
	 * @return void
	 */
	function execCommand($command, $checkSuccessful = true)
	{
		fputs($this->_conn, $command . "\n");
		$this->_checkSuccessful();
		echo "command '" . $command . "' sent", PHP_EOL;
	}
}

//	Get variables from HTTP GET.
if (isset($_GET["title"]) && isset($_GET["artist"])) {
	$title = $_GET["title"];
	$artist = $_GET["artist"];
} else {
	die('Artist / Title missing');
}

$ts = new TS3Radio('Joel-Win7', 25639, 'sjaohDZ0OPeS5UdmBgB1vZn94zA=');
$ts->setTitle($title);
$ts->setArtist($artist);
$ts->doStuff();


// Let's get cracking!
//	First, check this is actually TS3
$temp = getLine();
if ( $temp != "TS3 Client" )
//	If first line is not 'TS3 Client' die with error 'Not TS3'
	{
		die("Not TS3! \"" . $temp . "\"\n");
	}
	else
	{
		//	Appears to be TS3, dumping unwanted lines from welcome message
		getLine();
		getLine();
	}

//	Request list of all Client Handler IDs
execCommand("serverconnectionhandlerlist");

//	Store list of available connection handlers
$scHandlersString = getLine();
//	Verify
successful();

//	scHandlers = array from splitting raw string around pipe
$scHandlers = explode( "|", $scHandlersString );
print_r($scHandlers);
//	For each handler, run loop
foreach ($scHandlers as $thisHandler)
	{
		//	Select TSconnection
		execCommand("use " . $thisHandler);
		//	Dump spare line
		getLine();
		successful();

		//	Find out who we are
		execCommand("whoami");
		$temp = explode( " ", getLine() );
		successful();	// Somewhere between checking the command worked, and just dumping a useless line
		$clid = substr( $temp[0], 5);
		echo $thisHandler . " has CLID: " . $clid . "\n";

		//	Find and check our Unique ID
		execCommand("clientvariable client_unique_identifier clid=" . $clid);
		$temp = explode( " ", getLine() );
		//	Unique ID is everything after 25th char of 2nd returned variable
		$thisUID = substr( $temp[1], 25);
		successful();

		//	Check if this is UID is Radio or not
		if ( $thisUID == $radioUID )
		{
			if ( isset( $radioHandler ) )
			{
				die("Whoa! two radio connections? Ain't nobody got time for dat!\n");
			}
			else
			{
				$radioHandler = $thisHandler;
			}
		}
		else
		{
			echo "Nope.jpg -- This isn't the radio, carry on looking.\n";
		}
	}

//	Check that we did actually find the radio connection
if ( !isset( $radioHandler ) )
{
	die("We didn't find a radio user :(\n");
}

echo "The radio is using connection: " . $radioHandler . "\n";
//	Switch to correct connection handler
execCommand("use " . $radioHandler);
getLine();
successful();

//	Now the real Radio actions start happening!
switch ($artist) {
	case "SCREENINFO":
		//	Is screeninfo, just print title (message) to chat
		//	Send chat message to channel '<title/message>'
		chatMessage($title);
		break;
	case "RadioStream":
		//	Is a radio stream, print message to chat, and change client name
		//	Send chat message to channel '<title/message>'
		chatMessage("[b]Now Streaming:[/b] " . $title);
		//	Change name to name of stream
		break;
	case "":
		//	No artist given, don't bother outputting the artist or seperating dash
		//	Send chat message to channel 'now playing: <title>'
		chatMessage("[b]Now Playing:[/b] " . $title);
		break;
	Default:
		//	Artist did not match any presets, probably real, send output 'now playing' to chat
		//	send chat message to channel 'now playing: <title> <artist>'
		chatMessage("[b]Now Playing:[/b] " . $title . " - " . $artist);
		break;
}
//	Attempt to tell TS3 we are quitting
execCommand("quit");
//	Close connection
fclose($conn);
