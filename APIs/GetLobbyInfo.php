<?php

ob_start();
include "../HostFiles/Redirector.php";
include_once "../AccountFiles/AccountSessionAPI.php";
include_once "../CardDictionary.php";
include "../Libraries/HTTPLibraries.php";
include_once "../Assets/patreon-php-master/src/PatreonDictionary.php";
include "../Libraries/SHMOPLibraries.php";
include_once "../Libraries/PlayerSettings.php";
ob_end_clean();

if (!function_exists("DelimStringContains"))
{
  function DelimStringContains($str, $find, $partial = false)
  {
    $arr = explode(",", $str);
    for ($i = 0; $i < count($arr); ++$i)
    {
      if ($partial && str_contains($arr[$i], $find)) return true;
      else if ($arr[$i] == $find) return true;
    }
    return false;
  }
}

if (!function_exists("SubtypeContains"))
{
  function SubtypeContains($cardID, $subtype, $player = "")
  {
    $cardSubtype = CardSubtype($cardID);
    return DelimStringContains($cardSubtype, $subtype);
  }
}


SetHeaders();


$_POST = json_decode(file_get_contents('php://input'), true);
$gameName = TryPOST("gameName", 0);
$playerID = TryPOST("playerID", 0);
if ($playerID == 1 && isset($_SESSION["p1AuthKey"])) $authKey = $_SESSION["p1AuthKey"];
else if ($playerID == 2 && isset($_SESSION["p2AuthKey"])) $authKey = $_SESSION["p2AuthKey"];
else $authKey = TryPOST("authKey");

$response = new stdClass();
session_write_close();

if ($playerID != 1 && $playerID != 2)
{
  $response->error = "Invalid player ID";
  echo(json_encode($response));
  exit;
}

if (!file_exists("../Games/" . $gameName . "/GameFile.txt"))
{
  echo(json_encode(new stdClass()));
  exit;
}

ob_start();
include "../MenuFiles/ParseGamefile.php";
ob_end_clean();

$yourName = ($playerID == 1 ? $p1uid : $p2uid);
$theirName = ($playerID == 1 ? $p2uid : $p1uid);

$response->badges = [];

$response->amIActive = true; //Is the game waiting on me to do something?

if ($gameStatus == $MGS_ChooseFirstPlayer) $response->amIActive = $playerID == $firstPlayerChooser ? true : false;
else if ($playerID == 1 && $gameStatus < $MGS_ReadyToStart) $response->amIActive = false;
else if ($playerID == 2 && $gameStatus >= $MGS_ReadyToStart) $response->amIActive = false;

$contentCreator = ContentCreators::tryFrom(($playerID == 1 ? $p1ContentCreatorID : $p2ContentCreatorID));
$response->nameColor = ($contentCreator != null ? $contentCreator->NameColor() : "");
$response->displayName = ($yourName != "-" ? $yourName : "Player " . $playerID);


$deckFile = "../Games/" . $gameName . "/p" . $playerID . "Deck.txt";
$handler = fopen($deckFile, "r");
if ($handler)
{
  $material = GetArray($handler);
  $response->overlayURL = ($contentCreator != null ? $contentCreator->HeroOverlayURL($material[0]) : "");


  $response->deck = new stdClass();
  if (isset($material))
  {
    $response->deck->hero = $material[0];
    $response->deck->heroName = CardName($material[0]);
    $response->deck->material = [];
    sort($material);
    for ($i = 0; $i < count($material); ++$i)
    {
      $cardID = $material[$i];

      array_push($response->deck->material, $cardID);
    }
  }

  $response->format = $format;

  $response->deck->cards = GetArray($handler);
  //Remove deck cards that don't belong
  for ($i = count($response->deck->cards) - 1; $i >= 0; --$i)
  {
    if (CardType($response->deck->cards[$i]) == "D")
    {
      array_push($response->deck->demiHero, $response->deck->cards[$i]);
      unset($response->deck->cards[$i]);
    }
  }
  $response->deck->cards = array_values($response->deck->cards);

  $offhandSB = GetArray($handler);
  $weaponSB = GetArray($handler);
  $response->deck->cardsSB = GetArray($handler);
  //Remove deck cards that don't belong
  for ($i = count($response->deck->cardsSB) - 1; $i >= 0; --$i)
  {
    if (CardType($response->deck->cardsSB[$i]) == "D")
    {
      array_push($response->deck->demiHero, $response->deck->cardsSB[$i]);
      unset($response->deck->cardsSB[$i]);
    }
  }

  $response->deck->materialSB = [];
  //TODO Material SB

  $cardIndex = [];
  $response->deck->cardDictionary = [];
  foreach ($response->deck->cards as $card)
  {
    if (!array_key_exists($card, $cardIndex))
    {
      $cardIndex[$card] = "1";
      $dictionaryCard = new stdClass();
      $dictionaryCard->id = $card;
      $dictionaryCard->pitch = PitchValue($card);
      array_push($response->deck->cardDictionary, $dictionaryCard);
    }
  }

  fclose($handler);
}

echo json_encode($response);

exit;
