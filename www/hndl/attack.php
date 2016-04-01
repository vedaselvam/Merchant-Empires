<?php
/**
 * Handles attacking another player.
 *
 * @package [Redacted]Me
 * ---------------------------------------------------------------------------
 *
 * Merchant Empires by [Redacted] Games LLC - A space merchant game of war
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

	include_once('inc/page.php');
	include_once('inc/game.php');
	
	if (isset($_SESSION['form_id'])) {
		if (!isset($_REQUEST['form_id']) || $_SESSION['form_id'] != $_REQUEST['form_id']) {
			header('Location: viewport.php?rc=1181');
			die();
		}
	}
	
	$return_page = 'viewport';

	do { // dummy loop

		// Remove turns before doing work

		if ($spacegame['player']['turns'] < ATTACK_TURN_COST) {
			$return_codes[] = 1018;
			break;
		}

		$turn_cost = ATTACK_TURN_COST;
		$player_id = PLAYER_ID;

		$db = isset($db) ? $db : new DB;

		if (!($st = $db->get_db()->prepare("update players set turns = turns - ? where record_id = ?"))) {
			error_log(__FILE__ . '::' . __LINE__ . "Prepare failed: (" . $db->get_db()->errno . ") " . $db->get_db()->error);
			$return_codes[] = 1006;
			break;
		}
		
		$st->bind_param("ii", $turn_cost, $player_id);
		
		if (!$st->execute()) {
			$return_codes[] = 1006;
			error_log(__FILE__ . '::' . __LINE__ . " Query execution failed: (" . $db->get_db()->errno . ") " . $db->get_db()->error);
			break;
		}


	
		if ($spacegame['player']['level'] < MINIMUM_KILLABLE_LEVEL) {
			$return_codes[] = 1194;
			break;
		}

		if (!isset($_REQUEST['player_id']) || !is_numeric($_REQUEST['player_id']) || $_REQUEST['player_id'] <= 0) {
			$return_codes[] = 1014;
			break;
		}

		$player_id = $_REQUEST['player_id'];

		if (!isset($_REQUEST['solution_group']) || !is_numeric($_REQUEST['solution_group']) || $_REQUEST['solution_group'] <= 0) {
			$return_codes[] = 1189;
			break;
		}

		$solution_group = $_REQUEST['solution_group'];

		$db = isset($db) ? $db : new DB;

		$rs = $db->get_db()->query("select * from players where record_id = '" . $player_id . "' and x = '" . $spacegame['player']['x'] . "' and y = '" . $spacegame['player']['y'] . "' and base_id = '" . $spacegame['player']['base_id'] . "'");

		$rs->data_seek(0);

		if (!($player = $rs->fetch_assoc())) {
			$return_codes[] = 1014;
			break;
		}

		if ($player['ship_type'] <= 0) {
			$return_codes[] = 1195;
			break;
		}

		if ($player['level'] < MINIMUM_KILLABLE_LEVEL) {
			$return_codes[] = 1194;
			break;
		}

		include_once('inc/ships.php');

		$ship = $spacegame['ships'][$player['ship_type']];

		include_once('inc/solutions.php');
		
		if (!isset($spacegame['solution_groups'][$solution_group])) {
			$return_codes[] = 1189;
			break;
		}

		include_once('inc/cargo.php');
		include_once('inc/ranks.php');

		// Fire the weapons

		$time = PAGE_START_TIME;

		$total_damage = 0;

		$player_shields = $player['shields'];
		$player_armor = $player['armor'];

		$hitters = array();

		$message = '';
		$message .= $spacegame['races'][$spacegame['player']['race']]['caption'];
		$message .= ' ' . $spacegame['ranks'][$spacegame['player']['rank']]['caption'];
		$message .= ' ' . $spacegame['player']['caption'];
		$message .= ' in a';
		$message .= ' ' . $spacegame['races'][$spacegame['ships'][$spacegame['player']['ship_type']]['race']]['caption'];
		$message .= ' ' . $spacegame['ships'][$spacegame['player']['ship_type']]['caption'];

		if (strlen($spacegame['player']['ship_name']) <= 0) {
			$message .= ' "' . DEFAULT_SHIP_NAME . '"';
		}
		else {
			$message .= ' "' . $spacegame['player']['ship_name'] . '"';
		}

		$message .= ' has attacked';

		$message .= ' ' . $spacegame['races'][$player['race']]['caption'];
		$message .= ' ' . $spacegame['ranks'][$player['rank']]['caption'];
		$message .= ' ' . $player['caption'];
		$message .= ' in a';
		$message .= ' ' . $spacegame['races'][$spacegame['ships'][$player['ship_type']]['race']]['caption'];
		$message .= ' ' . $spacegame['ships'][$player['ship_type']]['caption'];

		if (strlen($player['ship_name']) <= 0) {
			$message .= ' "' . DEFAULT_SHIP_NAME . '"';
		}
		else {
			$message .= ' "' . $player['ship_name'] . '"';
		}

		$message .= ' in sector ' . $player['x'] . ',' . $player['y'] . ':<br />';
		$message .= '<br />';

		foreach ($spacegame['solution_groups'][$solution_group] as $solution_id) {

			$damage_caused = 0;

			$solution = $spacegame['solutions'][$solution_id];
			$elapsed = $time - $solution['fire_time'];

			if ($elapsed <= ATTACK_FLOOD_DELAY) {
				$return_codes[] = 1196;
				break 2;
			}

			// Record weapon firing no matter what

			if (!($st = $db->get_db()->prepare("update solutions set fire_time = ? where record_id = ?"))) {
				error_log(__FILE__ . '::' . __LINE__ . "Prepare failed: (" . $db->get_db()->errno . ") " . $db->get_db()->error);
				$return_codes[] = 1006;
				break;
			}
			
			$st->bind_param("ii", $time, $solution_id);
			
			if (!$st->execute()) {
				$return_codes[] = 1006;
				error_log(__FILE__ . '::' . __LINE__ . " Query execution failed: (" . $db->get_db()->errno . ") " . $db->get_db()->error);
				break;
			}

			$weapon = $spacegame['weapons'][$solution['weapon']];

			$message .= '<span class="weapon_caption">' . $weapon['caption'] . '</span>';

			$recharge = RECHARGE_TIME_PER_DAMAGE * $weapon['volley'] * ($weapon['shield_damage'] + $weapon['general_damage'] + $weapon['armor_damage']);
			$recharge += $spacegame['ship']['recharge'];

			if ($recharge < 1) {
				$recharge = 1;
			}

			if ($elapsed > $recharge) {
				$elapsed = $recharge;
			}

			$shield_damage = $weapon['shield_damage'];
			$general_damage = $weapon['general_damage'];
			$armor_damage = $weapon['armor_damage'];

			$accuracy = $weapon['accuracy'] * $elapsed / $recharge;

			$message .= ' at ' . round($accuracy * 100) . '%';

			if ($weapon['accuracy'] < 1.0) {
				// Projectile with a chance to miss
				
				if (mt_rand(0, 1000) > $accuracy * 1000) {
					$shield_damage = 0;
					$armor_damage = 0;
					$general_damage = 0;
				}
			}
			else {
				// Energy weapon with potency

				$shield_damage *= $accuracy;
				$armor_damage *= $accuracy;
				$general_damage *= $accuracy;
			}

			if ($player_shields > 0) {
				if ($shield_damage < $player_shields) {
					$player_shields -= $shield_damage;
					$damage_caused += $shield_damage;
				}
				else {
					$shield_damage -= $player_shields;
					$damage_caused += $player_shields;
					$player_shields = 0;
				}
			}

			if ($player_shields > 0) {
				if ($general_damage < $player_shields) {
					$player_shields -= $general_damage;	
					$damage_caused += $general_damage;
				}
				else {
					$general_damage -= $player_shields;
					$damage_caused += $player_shields;
					$player_shields = 0;
				}
			}

			if ($player_shields <= 0) {

				if ($player_armor > 0) {
					if ($general_damage < $player_armor) {
						$player_armor -= $general_damage;
						$damage_caused += $general_damage;	
					}
					else {
						$general_damage -= $player_armor;
						$damage_caused += $player_armor;
						$player_armor = 0;
					}
				}

				if ($player_armor > 0) {
					if ($armor_damage < $player_armor) {
						$player_armor -= $armor_damage;
						$damage_caused += $armor_damage;	
					}
					else {
						$armor_damage -= $player_armor;
						$damage_caused += $player_armor;
						$player_armor = 0;
					}
				}
			}

			$damage_caused = ceil($damage_caused);

			if ($damage_caused <= 0) {
				$message .= ' <span class="miss">*MISS*</span>';
			}
			else {
				$message .= ' causing ' . $damage_caused . ' damage';
			}
			
			if ($player_armor <= 0) {
				$message .= ' <span class="kill">*KILL*</span>';
			}

			$message .= '<br />';

			$total_damage += $damage_caused;
		}


		include_once('inc/combat.php');

		$hitters[] = array(
			'hitter' => $spacegame['player']['record_id'],
			'shield_damage' => $player['shields'] - $player_shields,
			'armor_damage' => $player['armor'] - $player_armor,
		);

		if (players_attack_player($player_id, $hitters)) {
			// Player is dead
			$serial = ($spacegame['player']['y'] * 1000) + $spacegame['player']['x'];
			player_log($player_id, $spacegame['actions']['death'], $complete_damage, $serial);
			break;
		}
		
		echo $message;
		$targets = array($player['record_id'], $spacegame['player']['record_id']);

		send_message($message, $targets, MESSAGE_EXPIRATION, 4);
		
	} while (false);
	
	
	
?>