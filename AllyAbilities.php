<?php

function PlayAlly($cardID, $player, $subCards = "-")
{
  $allies = &GetAllies($player);
  array_push($allies, $cardID);
  array_push($allies, AllyEntersPlayState($cardID, $player));
  array_push($allies, AllyHealth($cardID, $player));
  array_push($allies, 0); //Frozen
  array_push($allies, $subCards); //Subcards
  array_push($allies, GetUniqueId()); //Unique ID
  array_push($allies, AllyEnduranceCounters($cardID)); //Endurance Counters
  array_push($allies, 0); //Buff Counters
  array_push($allies, 1); //Ability/effect uses
  $index = count($allies) - AllyPieces();
  CurrentEffectAllyEntersPlay($player, $index);
  AllyEntersPlayAbilities($player);
  return $index;
}

function DealAllyDamage($targetPlayer, $index, $damage)
{
  $allies = &GetAllies($targetPlayer);
  if($allies[$index+6] > 0) {
    $damage -= 3;
    if($damage < 0) $damage = 0;
    --$allies[$index+6];
  }
  $allies[$index+2] -= $damage;
  if($damage > 0) AllyDamageTakenAbilities($targetPlayer, $index);
  if($allies[$index+2] <= 0) DestroyAlly($targetPlayer, $index);
}

function RemoveAlly($player, $index)
{
  return DestroyAlly($player, $index, $true);
}

function DestroyAlly($player, $index, $skipDestroy = false, $fromCombat = false)
{
  global $combatChain, $mainPlayer;
  $allies = &GetAllies($player);
  if(!$skipDestroy) {
    AllyDestroyedAbility($player, $index);
  }
  AllyLeavesPlayAbility($player, $index);
  if(IsSpecificAllyAttacking($player, $index) || (IsSpecificAllyAttackTarget($player, $index) && !$fromCombat)) {
    CloseCombatChain();
  }
  $cardID = $allies[$index];
  if($cardID == "075L8pLihO") AddMemory($cardID, $player, "PLAY", "DOWN");
  else AddGraveyard($cardID, $player, "PLAY");
  for($j = $index + AllyPieces() - 1; $j >= $index; --$j) unset($allies[$j]);
  $allies = array_values($allies);
  return $cardID;
}

function AllyAddGraveyard($player, $cardID, $subtype)
{
  if(CardType($cardID) != "T") {
    $set = substr($cardID, 0, 3);
    $number = intval(substr($cardID, 3, 3));
    $number -= 400;
    if($number < 0) return;
    $id = $number;
    if($number < 100) $id = "0" . $id;
    if($number < 10) $id = "0" . $id;
    $id = $set . $id;
    if(!SubtypeContains($id, $subtype, $player)) return;
    AddGraveyard($id, $player, "PLAY");
  }
}

function AllyEntersPlayState($cardID, $player)
{
  if(SearchCurrentTurnEffects("dxAEI20h8F", $player)) return 1;
  switch($cardID)
  {
    case "2Q60hBYO3i": return 1;
    case "GXeEa0pe3B": return 1;//Rebellious Bull
    case "G5E0PIUd0W": return 1;//Artificer's Opus
    default: return 2;
  }
}

function AllyEntersPlayAbilities($player)
{
  $allies = &GetAllies($player);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    switch($allies[$i])
    {
      case "cVRIUJdTW5"://Meadowbloom Dryad
        AddDecisionQueue("MULTIZONEINDICES", $player, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $player, "<-", 1);
        AddDecisionQueue("MZOP", $player, "BUFFALLY", 1);
        break;
      default: break;
    }
  }
}

function AllyPride($cardID)
{
  switch($cardID)
  {
    case "hJ2xh9lNMR": return 2;//Gray Wolf
    case "GXeEa0pe3B": return 3;//Rebellious Bull
    case "MmbQQdsRhi": return 5;//Enraged Boars
    case "1Sl4Gq2OuV": return 4;//Blue Slime
    case "gKVMTAeLXQ": return 5;//Blazing Direwolf
    case "dZ960Hnkzv": return 10;//Vertus, Gaia's Roar
    case "HWFWO0TB8l": return 5;//Tempest Silverback
    case "krgjMyVHRd": return 6;//Lakeside Serpent
    case "075L8pLihO": return 5;//Arima, Gaia's Wings
    case "wFH1kBLrWh": return 7;//Arcane Elemental
    default: return -1;
  }
}

function AllyHealth($cardID, $playerID="")
{
  $health = CardLife($cardID);
  switch($cardID)
  {
    case "HWFWO0TB8l": if(IsClassBonusActive($playerID, "TAMER")) $health += 2;//Tempest Silverback
    case "7NMFSRR5V3": if(SearchCount(SearchAllies($playerID, subtype:"BEAST")) > 0) $health += 1;//Fervent Beastmaster
    case "csMiEObm2l": if(CharacterLevel($playerID) >= 3 && IsClassBonusActive($playerID, "WARRIOR")) $health += 1;//Strapping Conscript
    default: break;
  }
  return $health;
}

function AllyLeavesPlayAbility($player, $index)
{
  $allies = &GetAllies($player);
  $cardID = $allies[$index];
  switch($cardID)
  {
    case "XZFXOE9sEV"://Zephyr Assistant
      PlayAura("ENLIGHTEN", $currentPlayer);
      break;
    default: break;
  }
}

function AllyDestroyedAbility($player, $index)
{
  global $mainPlayer;
  $allies = &GetAllies($player);
  $cardID = $allies[$index];
  OnKillAbility();
  switch($cardID) {
    case "iD8qbpA8z5"://Library Witch
      WriteLog("Player $player drew a card from Library Witch");
      Draw($player);
      break;
    case "l64yfOVhkp"://Prodigious Burstmage
      Draw($player);
      PummelHit($player, fromDQ:IsDecisionQueueActive());
      break;
    case "pnDhApDNvR"://Magus Disciple
      if(IsClassBonusActive($player, "MAGE") || IsClassBonusActive($player, "CLERIC")) Draw($player);
      break;
    default: break;
  }
}

function OnKillAbility()
{
  global $combatChain, $mainPlayer;
  if(count($combatChain) == 0) return;
  switch($combatChain[0])
  {
    case "71i7d3JB9A": if(CharacterLevel($mainPlayer) >= 2) { WriteLog("Drew from Clean Cut"); Draw($mainPlayer); } break;
    default: break;
  }
}

function AllyStartTurnAbilities($player)
{
  $allies = &GetAllies($player);
  for($i = 0; $i < count($allies); $i += AllyPieces()) {
    switch($allies[$i]) {
      case "075L8pLihO": BuffAlly($player, $i, 3); break;
      case "CvvgJR4fNa": AddCurrentTurnEffect("CvvgJR4fNa", $player, "PLAY", $allies[$i+5]); break;//Patient Rogue
      case "6gN5KjqRW5": if(IsClassBonusActive($player, "WARRIOR")) AddDurabilityCounters($player, 1); break;//Weaponsmith
      default: break;
    }
  }
}

function BuffAlly($player, $index, $amount=1)
{
  $allies = &GetAllies($player);
  $allies[$index+7] += $amount;//Buff counters
  $allies[$index+2] += $amount;//Life
}

function AllyEnduranceCounters($cardID)
{
  switch($cardID) {
    case "UPR417": return 1;
    default: return 0;
  }
}

function AllyDamagePrevention($player, $index, $damage)
{
  $allies = &GetAllies($player);
  $cardID = $allies[$index];
  $canBePrevented = CanDamageBePrevented($player, $damage, "");
  switch($cardID) {
    case "UPR417":
      if($allies[$index + 6] > 0) {
        if($damage > 0) --$allies[$index + 6];
        if($canBePrevented) $damage -= 3;
        if($damage < 0) $damage = 0;
      }
      return $damage;
    default: return $damage;
  }
}

//NOTE: This is for ally abilities that trigger when any ally attacks (for example miragai GRANTS an ability)
function AllyAttackAbilities($attackID)
{
  global $mainPlayer, $combatChainState, $CCS_AttackUniqueID;
  $allies = &GetAllies($mainPlayer);
  for($i = 0; $i < count($allies); $i += AllyPieces()) {
    switch($allies[$i]) {
      case "rPpLwLPGaL": if($allies[$i+5] != $combatChainState[$CCS_AttackUniqueID] && SubtypeContains($attackID, "HUMAN", $mainPlayer)) AddCurrentTurnEffect("rPpLwLPGaL", $mainPlayer, "PLAY"); break;//Phalanx Captain
      case "IAkuSSnzYB"://Banner Knight
        if($allies[$i+5] != $combatChainState[$CCS_AttackUniqueID] && IsClassBonusActive($mainPlayer, "WARRIOR") && CharacterLevel($mainPlayer) >= 2) AddCurrentTurnEffect("IAkuSSnzYB", $mainPlayer, "PLAY");
        break;
      default: break;
    }
  }
}

function AllyPlayCardAbility($cardID, $player="")
{
  global $currentPlayer;
  if($player == "") $player = $currentPlayer;
  $allies = &GetAllies($player);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    switch($allies[$i])
    {
      case "aKgdkLSBza"://Wilderness Harpist
        if(SubtypeContains($cardID, "HARMONY") || SubtypeContains($cardID, "MELODY")) AddCurrentTurnEffect("aKgdkLSBza", $player);
        break;
      default: break;
    }
  }
}

function IsAlly($cardID, $player="")
{
  global $currentPlayer;
  if($player == "") $player = $currentPlayer;
  return CardTypeContains($cardID, "ALLY", $player);
}

//NOTE: This is for the actual attack abilities that allies have
function SpecificAllyAttackAbilities($attackID)
{
  global $mainPlayer, $combatChainState, $CCS_WeaponIndex;
  $allies = &GetAllies($mainPlayer);
  $i = $combatChainState[$CCS_WeaponIndex];
  switch($allies[$i]) {
    case "DsiRzt0trX"://Hasty Messenger
      PummelHit($mainPlayer, true);
      AddDecisionQueue("DRAW", $mainPlayer, "-", 1);
      break;
    case "gKVMTAeLXQ"://Blazing Direwolf
      if(IsClassBonusActive($mainPlayer, "TAMER")) DealArcane(2, 2, "PLAYCARD", "gKVMTAeLXQ", true, $mainPlayer);
      break;
    case "wFH1kBLrWh"://Arcane Elemental
      AddCurrentTurnEffect("wFH1kBLrWh", $mainPlayer);
      break;
    default: break;
  }
}

function AllyDamageTakenAbilities($player, $i)
{
  $allies = &GetAllies($player);
  switch($allies[$i]) {
    case "1Sl4Gq2OuV"://Blue slime
      $allies[$i+2] += 1;
      $allies[$i+7] += 1;
      WriteLog(CardLink($allies[$i], $allies[$i]) . " got a buff counter");
      break;
    default: break;
  }
}

function AllyTakeDamageAbilities($player, $index, $damage, $preventable)
{
  $allies = &GetAllies($player);
  $otherPlayer = ($player == 1 ? 2 : 1);
  //CR 2.1 6.4.10f If an effect states that a prevention effect can not prevent the damage of an event, the prevention effect still applies to the event but its prevention amount is not reduced. Any additional modifications to the event by the prevention effect still occur.
  $type = "-";//Add this if it ever matters
  $preventable = CanDamageBePrevented($otherPlayer, $damage, $type);
  for($i = count($allies) - AllyPieces(); $i >= 0; $i -= AllyPieces()) {
    $remove = false;
    switch($allies[$i]) {
      default: break;
    }
    if($remove) DestroyAlly($player, $i);
  }
  if($damage <= 0) $damage = 0;
  return $damage;
}

function AllyBeginTurnEffects()
{
  global $mainPlayer;
  $mainAllies = &GetAllies($mainPlayer);
  for($i = 0; $i < count($mainAllies); $i += AllyPieces()) {
    if($mainAllies[$i+1] != 0) {
      if($mainAllies[$i+3] != 1) $mainAllies[$i+1] = 2;
    }
  }
}

function AllyBeginEndTurnEffects()
{
  global $mainPlayer, $defPlayer;
  //Reset health for all allies
  $mainAllies = &GetAllies($mainPlayer);
  for($i = count($mainAllies) - AllyPieces(); $i >= 0; $i -= AllyPieces()) {
    if($mainAllies[$i+1] != 0) {
      if(HasVigor($mainAllies[$i], $mainPlayer)) $mainAllies[$i+1] = 2;
      $mainAllies[$i+2] = AllyHealth($mainAllies[$i], $mainPlayer) + $mainAllies[$i+7];
      $mainAllies[$i+3] = 0;
      $mainAllies[$i+8] = 1;
    }
    switch($mainAllies[$i])
    {
      case "mA4n0Z7BQz"://Mistbound Watcher
        if(IsClassBonusActive($mainPlayer, "MAGE")) PlayAura("ENLIGHTEN", $mainPlayer);
        break;
      case "wFH1kBLrWh"://Arcane Elemental
        if(SearchCurrentTurnEffects("wFH1kBLrWh", $mainPlayer))
        {
          RemoveAlly($mainPlayer, $i);
          BanishCardForPlayer("wFH1kBLrWh", $mainPlayer, "PLAY");
        }
        break;
      default: break;
    }
  }
  $defAllies = &GetAllies($defPlayer);
  for($i = 0; $i < count($defAllies); $i += AllyPieces()) {
    if($defAllies[$i+1] != 0) {
      $defAllies[$i+2] = AllyHealth($defAllies[$i], $defPlayer) + $defAllies[$i + 7];
      $defAllies[$i+8] = 1;
    }
  }
}

function AllyLevelModifiers($player)
{
  $allies = &GetAllies($player);
  $levelModifier = 0;
  for($i = count($allies) - AllyPieces(); $i >= 0; $i -= AllyPieces()) {
    $remove = false;
    switch($allies[$i]) {
      case "qxbdXU7H4Z": if(SearchCount(SearchAllies($player, "", "BEAST")) + SearchCount(SearchAllies($player, "", "ANIMAL")) > 0) ++$levelModifier; break;
      case "yDARN8eV6B": if(IsClassBonusActive($player, "MAGE")) ++$levelModifier; break;//Tome of Knowledge
      case "izGEjxBPo9": if(SearchCount(SearchAllies($player, "", "BEAST")) + SearchCount(SearchAllies($player, "", "ANIMAL")) > 0) ++$levelModifier; break;
      case "q2okpDFJw5": if(SearchCount(SearchAllies($player, "", "BEAST")) + SearchCount(SearchAllies($player, "", "ANIMAL")) > 0) ++$levelModifier; break; //Energetic Beastbonder
      case "pnDhApDNvR": ++$levelModifier; break;//Magus Disciple
      case "1i6ierdDjq": if(SearchCount(SearchAllies($player, "", "BEAST")) + SearchCount(SearchAllies($player, "", "ANIMAL")) > 0) ++$levelModifier; break;//Flamelash Subduer
      default: break;
    }
    if($remove) DestroyAlly($player, $i);
  }
  return $levelModifier;
}

function AllyEndTurnAbilities()
{
  global $mainPlayer;
  $allies = &GetAllies($mainPlayer);
  for($i = count($allies) - AllyPieces(); $i >= 0; $i -= AllyPieces()) {
    switch($allies[$i]) {

      default: break;
    }
  }
}

function GiveAlliesHealthBonus($player, $amount)
{
  $allies = &GetAllies($player);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    $allies[$i+2] += $amount;
  }
}
