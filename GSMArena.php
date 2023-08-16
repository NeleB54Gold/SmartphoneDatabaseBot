<?php

class GSMArena
{
	# Database to cache results (Redis)
	private $db = [];
	# Endpoint of your GSMArena PHP API [https://github.com/NeleB54Gold/GSMArenaAPI]
	private $endpoint = 'http://neleb54gold.altervista.org/Smartphones/index.php';
	
	# Set configs
	public function __construct ($db, $bot) {
		if (is_a($db, 'Database')) $this->db = $db;
		if (is_a($bot, 'TelegramBot')) $this->requests = $bot;
	}
	
	# Delete all cached results
	function emptyCache() {
		return $this->db->rdel($this->db->rkeys($this->endpoint . '*'));
	}
	
	# Get device informations by slug
	function getDevice ($device = '') {
		$url = $this->endpoint . '?' . http_build_query(['slug' => $device]);
		if (is_a($this->db, 'Database') && $this->db->configs['redis']['status'] && ($rr = $this->db->rget($url)) && ($r = json_decode($rr, 1))) {
		} else {
			$r = $this->requests->request($url, 0, 0, 1, 5);
			if (is_a($this->db, 'Database') && $this->db->configs['redis']['status']) $this->db->rset($url, json_encode($r), (60 * 60 * 2));
		}
		file_put_contents('stats.txt', (file_get_contents('stats.txt') + 1));
		return $r;
	}s

	# Get devices by search
	function searchDevice ($query, $limit = 50, $offset = 0) {
		$url = $this->endpoint . '?' . http_build_query(['query' => $query]);
		if (is_a($this->db, 'Database') && $this->db->configs['redis']['status'] && ($rr = $this->db->rget($url)) && ($r = json_decode($rr, 1))) {
			$r['redis'] = true;
		} else {
			$r = $this->requests->request($url, 0, 0, 1, 5);
			if (is_a($this->db, 'Database') && $this->db->configs['redis']['status']) $this->db->rset($url, json_encode($r), (60 * 60 * 2));
		}
		if ($limit) {
			$limited = [];
			foreach (range($offset * $limit, ($offset * $limit) + $limit - 1) as $id) {
				if (isset($r['data'][$id])) $limited[] = $r['data'][$id];
			}
			$r['data'] = $limited;
		}
		file_put_contents('stats.txt', (file_get_contents('stats.txt') + 1));
		if ($query == 'samsung') file_put_contents('test.json', json_encode($r));
		return $r;
	}
}

?>
