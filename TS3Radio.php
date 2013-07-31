<?php
  
	//////////////////////////////////////////////////////////////////////////////////////////
	//				~~~~ToDo~~~~						//
	//	Add RADIOSTREAM functionality	~50%~						//
	//			Chat only implemented - still want name				//
	//		Either check and reset, or just reset name.				//
	//	Move chat message and command sends to functions?				//
	// Add functionality to loop through if more than one radio exists?			//
	//											//
	//				~~~Bugs~~~						//
	// Script crashes if disconnected TS tab open 						//
	//////////////////////////////////////////////////////////////////////////////////////////


	//	Require authentication password
	require_once("auth.php");
	
	//	Get variables from HTTP GET
	if ( isset($_GET["title"]) && isset($_GET["artist"]) )
	{
		$title = $_GET["title"];
		$artist = $_GET["artist"];

	}
	else
	{
		//	If variables aren't set, we have nothing to do
		die("Suiciding: Artist / Title missing");
	}
	
	//	HostName / IP of machine TS3 is running on (can be localhost)
	$clientHost = "Joel-Win7";
	//	Port to connect to (Default / fixed value is 25639)
	$clientPort = 25639;
	//	Unique ID of Radio (Settings > Identities > Unique ID)
	$radioUID = "sjaohDZ0OPeS5UdmBgB1vZn94zA=";
	
	//////////////////////////////////////////////////////////////////////////
	//		Nothing should need editing below this point		//
	//		(OK, maybe JUST the find and replace arrays)		//
	//////////////////////////////////////////////////////////////////////////
	
	//	Arrays of elements to replace for TS3 message string
	$find = array(" ","&","'");
	$replace = array("\s","\&","\'");
	
	//	connection = Open socket to ( host, port, rtnErrNo, rtnErrStr, timeout )
	$conn = fsockopen("$clientHost", $clientPort, $errno, $errstr, 10);
	
	function getLine()
		{
			global $conn;
			$rawline = fgets($conn);
			//	Strip newlines and carriage returns
			$line = str_replace(array("\r", "\n"), '', $rawline );
			//	Echo the line, and then newline
			echo $line;
			echo "\n";
			//	Return the line for use as variable if needed
			return $line;
		}
		
	function successful()
		{
			global $conn;
			$temp = getLine();
			//	If not correct response to signify succesfull command, close connection and kill PHP quoting error
			if ( $temp != "error id=0 msg=ok" )
			{
				//	Attempt to tell TS3 we are quitting
				execCommand("quit");
				//	Close connection
				fclose($conn);
				//	Kill PHP script, quoting error from TS3
				die("Suiciding: Unexpected TS3 error: \"" . $temp . "\"\n");
			}
		}

	function chatMessage($rawString)
		{
			//global $artist;
			//global $title;
			global $find;
			global $replace;
			
			$string = str_replace($find,$replace,$rawString);
			execCommand("sendtextmessage targetmode=2 msg=" . $string);
		}
	function execCommand($command)
		{
			global $conn;
			fputs($conn, "$command" . "\n");
			echo "command '" . $command . "' sent";
		}
// Let's get cracking!
	//	Check we can even connect...
	if ( !$conn )
		{
			die("Suiciding: Connection Failed");
		}

	//	First, check this is actually TS3
	$temp = getLine();
	if ( $temp != "TS3 Client" )

	//	If first line is not 'TS3 Client' die with error 'Not TS3'
		{
			die("Suiciding: Not TS3! \"" . $temp . "\"\n");
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
			//	CLID = first array item, after 5th char
			$clid = substr($temp[0], 5);
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
					die("Whoa! two radio connections? Ain't nobody got time for dat! Suiciding\n");
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
		die("Suiciding: We didn't find a radio user :(\n");
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
?>
