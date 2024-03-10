<?php

include "../HostFiles/Redirector.php";
include "../Libraries/HTTPLibraries.php";
include "../Libraries/SHMOPLibraries.php";
include "../Libraries/NetworkingLibraries.php";
include "../GameLogic.php";
include "../GameTerms.php";
include "../Libraries/StatFunctions.php";
include "../Libraries/PlayerSettings.php";
include "../Libraries/UILibraries2.php";
include "../AI/CombatDummy.php";
include_once "../includes/dbh.inc.php";
include_once "../includes/functions.inc.php";
include_once "../MenuFiles/StartHelper.php";

SetHeaders();


$response = new stdClass();
session_start();
$_POST = json_decode(file_get_contents('php://input'), true);

if($_POST == NULL) {
  $response->error = "Parameters were not passed";
  echo json_encode($response);
  exit;
}

$gameName = $_POST["gameName"];
$playerID = $_POST["playerID"];
if ($playerID == 1 && isset($_SESSION["p1AuthKey"])) $authKey = $_SESSION["p1AuthKey"];
else if ($playerID == 2 && isset($_SESSION["p2AuthKey"])) $authKey = $_SESSION["p2AuthKey"];
else if (isset($_POST["authKey"])) $authKey = $_POST["authKey"];
if (!IsGameNameValid($gameName)) {
  $response->error = "Invalid game name.";
  echo json_encode($response);
  exit;
}
$submissionString = $_POST["submission"];

include "../MenuFiles/ParseGamefile.php";
include "../MenuFiles/WriteGamefile.php";

$targetAuth = ($playerID == 1 ? $p1Key : $p2Key);
if ($authKey != $targetAuth) {
  $response->error = "Invalid Auth Key";
  echo json_encode($response);
  exit;
}

$submission = json_decode($submissionString);
var_dump($submission);

$character = $submission->hero;
$deck = (isset($submission->selectedDeck) ? implode(" ", $submission->selectedDeck) : "");
//TODO: parse material/properly handle sideboarding

$playerDeck = $submission->selectedDeck;
$deckCount = count($playerDeck);
if($deckCount < 60 && ($format == "cc" || $format == "compcc" || $format == "llcc")) {
  $response->status = "FAIL";
  $response->deckError = "Unable to submit player " . $playerID . "'s deck. " . $deckCount . " cards selected is below the minimum.";
  echo json_encode($response);
  exit;
}
if($deckCount < 40 && ($format == "blitz" || $format == "compblitz" || $format == "commoner" || $format == "llblitz")) {
  $response->status = "FAIL";
  $response->deckError = "Unable to submit player " . $playerID . "'s deck. " . $deckCount . " cards selected is below the minimum.";
  echo json_encode($response);
  exit;
}
if($deckCount > 40 && ($format == "blitz" || $format == "compblitz" || $format == "llblitz")) {
  $response->status = "FAIL";
  $response->deckError = "Unable to submit player " . $playerID . "'s deck. " . $deckCount . " cards selected is above the maximum.";
  echo json_encode($response);
  exit;
}

$filename = "../Games/" . $gameName . "/p" . $playerID . "Deck.txt";
$deckFile = fopen($filename, "w");
fwrite($deckFile, $character . "\r\n");

fwrite($deckFile, $deck . "\r\n");
fwrite($deckFile, (isset($submission->inventory) ? implode(" ", $submission->inventory) : ""));
fclose($deckFile);

if($playerID == 1) $p1SideboardSubmitted = "1";
else if($playerID == 2) $p2SideboardSubmitted = "1";

if($p1SideboardSubmitted == "1" && $p2SideboardSubmitted == "1") {
  $gameStatus = $MGS_ReadyToStart;

  //First initialize the initial state of the game
  $filename = "../Games/" . $gameName . "/gamestate.txt";
  $handler = fopen($filename, "w");
  fwrite($handler, "20 20\r\n"); //Player health totals

  //Player 1
  $p1DeckHandler = fopen("../Games/" . $gameName . "/p1Deck.txt", "r");
  initializePlayerState($handler, $p1DeckHandler, 1);
  fclose($p1DeckHandler);

  //Player 2
  $p2DeckHandler = fopen("../Games/" . $gameName . "/p2Deck.txt", "r");
  initializePlayerState($handler, $p2DeckHandler, 2);
  fclose($p2DeckHandler);

  WriteGameFile();
  SetCachePiece($gameName, $playerID + 1, strval(round(microtime(true) * 1000)));
  SetCachePiece($gameName, $playerID + 3, "0");
  SetCachePiece($gameName, $playerID + 6, $character);
  SetCachePiece($gameName, 14, $gameStatus);
  GamestateUpdated($gameName);


  ob_start();
  $filename = "../Games/" . $gameName . "/gamestate.txt";
  include "../ParseGamestate.php";
  include "../StartEffects.php";
  ob_end_clean();

  //Update the game file to show that the game has started and other players can join to spectate
  $gameStatus = $MGS_GameStarted;
}

$response->status = "OK";

WriteGameFile();
GamestateUpdated($gameName);

echo json_encode($response);
