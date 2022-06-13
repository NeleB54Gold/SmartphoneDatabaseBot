<?php

# Ignore inline messages (via @)
if ($v->via_bot) die;

# Start GSMArena class
$gsm = new GSMArena($db, $bot);

# Search for device
if (strpos($v->query_data, 'device_') === 0) {
	$device = str_replace('device_', '', $v->query_data);
	if (!$device) {
		$bot->answerCBQ($v->query_id);
		die;
	}
	$r = $gsm->getDevice($device);
	if ($r['status'] !== 'success') {
		$bot->answerCBQ($v->query_id, '⚠️ Service offline!', 1);
		die;
	}
	$bot->answerCBQ($v->query_id, false, '');
	$text = '📲 ' . $bot->bold($r['title'], 1);
	if (isset($r['data']['tests'])) unset($r['data']['tests']);
	foreach ($r['data'] as $title => $args) {
		$ttitle = $tr->getTranslation($title);
		if ($ttitle == '🤖') {
			$ttitle = str_replace('_', ' ', $title);
			$ttitle[0] = strtoupper($ttitle[0]);
		}
		$text .= PHP_EOL . PHP_EOL . $bot->bold($ttitle, 1);
		foreach ($args as $subtitle => $arg) {
			if (!$subtitle) {
				
			} else {
				$arg = str_replace('<br>', PHP_EOL, $arg);
				$arg = str_replace('&thinsp;', '', $arg);
				$arg = str_replace(PHP_EOL, PHP_EOL . '   ', $arg);
				$arg = str_replace(' / ', '/', $arg);
				if ($subtitle == 'loudspeaker_') $subtitle = 'loudspeaker';
				$tsubtitle = $tr->getTranslation($subtitle);
				if ($tsubtitle == '🤖') {
					$tsubtitle = str_replace('_', ' ', $subtitle);
					$tsubtitle[0] = strtoupper($tsubtitle[0]);
				}
				$text .= PHP_EOL . '  ' . $tsubtitle . ': ' . $arg;
			}
		}
	}
	if ($r['img']) $linkph = $bot->text_link('&#8203;', $r['img']);
	$buttons[][] = $bot->createInlineButton('💬 ' . $tr->getTranslation('shareButton'), $r['title']);
	if (!$v->message_id) $v->message_id = $v->inline_message_id;
	$bot->editText($v->chat_id, $v->message_id, $linkph . $text);
	die;
}

# Private chat commands
elseif ($v->chat_type == 'private') {
	if ($bot->configs['database']['status'] && $user['status'] !== 'started') $db->setStatus($v->user_id, 'started');
	
	# Delete cached results
	if ($v->isAdmin() && $v->command == 'delcache') {
		$gsm->emptyCache();
		$bot->sendMessage($v->chat_id, 'Done');
	} 
	# Start message
	elseif ($v->command == 'start' || $v->command == 'start inline' || $v->query_data == 'home') {
		$t = $bot->bold('ℹ️ Smartphone Database Bot') . PHP_EOL . $tr->getTranslation('start') . PHP_EOL . '@NeleBots';
		$buttons[] = [
			$bot->createInlineButton('🔍 ' . $tr->getTranslation('searchButton'), '', 'switch_inline_query_current_chat'),
			$bot->createInlineButton('💬 ' . $tr->getTranslation('shareButton'), '', 'switch_inline_query')
		];
		$buttons[][] = $bot->createInlineButton('🔡 ' . $tr->getTranslation('languageButton'), 'changeLanguage');
		if ($v->command) {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		} else {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($cbid, '', false);
		}
	}
	# About/Help commmand
	elseif ($v->command == 'about' || $v->command == 'help') {
		$stats = file_get_contents('stats.txt');
		$php = explode('-', phpversion(), 2)[0];
		$buttons[][] = $bot->createInlineButton('☕️ Buy me a coffee', 'https://paypal.me/pools/c/8ulfHwcFZV', 'url');
		$bot->sendMessage($v->chat_id, $tr->getTranslation('about', [$php, $stats]), $buttons);
	}
	# Change language
	elseif (strpos($v->query_data, 'changeLanguage') === 0) {
		$langnames = [
			'en' => '🇬🇧 English',
			'es' => '🇪🇸 Español',
			'ar' => '🇩🇿 عربى',
			'fa' => '🇮🇷 فارسی',
			'fr' => '🇫🇷 Français',
			'it' => '🇮🇹 Italiano',
			'ru' => '🇷🇺 Pусский',
			'uz-UZ' => '🇺🇿 O\'zbek'
		];
		if (strpos($v->query_data, 'changeLanguage-') === 0) {
			$select = str_replace('changeLanguage-', '', $v->query_data);
			if (in_array($select, array_keys($langnames))) {
				$tr->setLanguage($select);
				$user['lang'] = $select;
				$db->query('UPDATE users SET lang = ? WHERE id = ?', [$user['lang'], $user['id']]);
			}
		}
		$langnames[$user['lang']] .= ' ✅';
		$t = '🔡 Select your language';
		$formenu = 2;
		$mcount = 0;
		foreach ($langnames as $lang_code => $name) {
			if (isset($buttons[$mcount]) && count($buttons[$mcount]) >= $formenu) $mcount += 1;
			$buttons[$mcount][] = $bot->createInlineButton($name, 'changeLanguage-' . $lang_code);
		}
		$buttons[][] = $bot->createInlineButton('◀️ ' . $tr->getTranslation('back'), 'home');
		$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
		$bot->answerCBQ($v->query_id);
	}
	# Search for smartphones
	elseif ($v->text && !$v->query_data) {
		$r = $gsm->searchDevice($v->text);
		if ($r['status'] !== 'success') {
			$bot->sendMessage($v->chat_id, '⚠️ Service offline!');
			die;
		}
		if (isset($r['data'][0])) {
			$data = $r['data'];
			$mcount = 0;
			if (isset($r['data'][5])) {
				$formenu = 2;
			} else {
				$formenu = 1;
			}
			foreach($data as $sp) {
				if (isset($buttons[$mcount]) && count($buttons[$mcount]) >= $formenu) $mcount += 1;
				$buttons[$mcount][] = $bot->createInlineButton($sp['title'], 'device_' . $sp['slug']);
            }
			$bot->sendMessage($v->chat_id, $tr->getTranslation('selectDevice'), $buttons);
        } else {
			$bot->sendMessage($v->chat_id, $tr->getTranslation('deviceNotFound'), $buttons);
		}
	}
	# Delete unknown messages
	else {
		if ($v->message_id) $bot->deleteMessage($v->chat_id, $v->message_id);
	}
}

# Inline commands
elseif ($v->update['inline_query']) {
	$results = [];
	$sw_text = $tr->getTranslation('typeDeviceName');
	$sw_arg = 'inline'; // The message the bot receive is '/start inline'
	if ($v->query) {
		$r = $gsm->searchDevice($v->query);
		if (isset($r['data'][0])) {
			$data = $r['data'];
			$t = 'Loading...';
			$buttons[][] = $bot->createInlineButton('🔄', 'device_' . $sp['slug']);
			foreach($data as $sp) {
				if (count($results) < 50) {
					$d = $r[$num]; 
					$img = "https://fdn2.gsmarena.com/vv/bigpic/" . str_replace('_', '-', explode("-", $sp['slug'], 2)[0]) . ".jpg";
					$menu = [];
					if (@getimagesize($img)) {
					} else {
						$img = "https://fdn2.gsmarena.com/vv/pics/" . explode("_", $sp['slug'], 2)[0] . "/" . str_replace("_", "-", explode("-", $sp['slug'], 2)[0]) . ".jpg";
						if (@getimagesize($img)) {
						} else {
							$img = "https://telegra.ph/file/3d0d201b23992330189d2.jpg";
						}
					}
					$results[] = $bot->createInlineArticle(
						$sp['slug'],
						$sp['title'],
						'',
						$bot->createTextInput($t),
						$buttons,
						0, 0,
						$img
					);
				}
			}
   }
	}
	if (!empty($v->query) && $r['status'] !== 'success') {
		$sw_text = '⚠️ Try me in private chat!';
	}
	$bot->answerIQ($v->id, $results, $sw_text, $sw_arg);
}

# Send Inline results
elseif ($v->update['chosen_inline_result']) {
	$device = str_replace('device_', '', $v->id);
	if (!$device) die;
	$r = $gsm->getDevice($device);
	$text = '📲 ' . $bot->bold($r['title'], 1);
	if (isset($r['data']['tests'])) unset($r['data']['tests']);
	foreach ($r['data'] as $title => $args) {
		$ttitle = $tr->getTranslation($title);
		if ($ttitle == '🤖') {
			$ttitle = str_replace('_', ' ', $title);
			$ttitle[0] = strtoupper($ttitle[0]);
		}
		$text .= PHP_EOL . PHP_EOL . $bot->bold($ttitle, 1);
		foreach ($args as $subtitle => $arg) {
			if ($subtitle) {
				if ($subtitle == 'loudspeaker_') $subtitle = 'loudspeaker';
				$arg = str_replace('<br>', PHP_EOL, $arg);
				$arg = str_replace('&thinsp;', '', $arg);
				$arg = str_replace(PHP_EOL, PHP_EOL . '   ', $arg);
				$arg = str_replace(' / ', '/', $arg);
				$tsubtitle = $tr->getTranslation($subtitle);
				if ($tsubtitle == '🤖') {
					$tsubtitle = str_replace('_', ' ', $subtitle);
					$tsubtitle[0] = strtoupper($tsubtitle[0]);
				}
				$text .= PHP_EOL . '  ' . $tsubtitle . ': ' . $arg;
			}
		}
	}
	if ($r['img']) $linkph = $bot->text_link('&#8203;', $r['img']);
	$buttons[][] = $bot->createInlineButton('💬 ' . $tr->getTranslation('shareButton'), $r['title'], 'switch_inline_query');
	$bot->editText($v->chat_id, $v->message_id, $linkph . $text, $buttons);
}

?>