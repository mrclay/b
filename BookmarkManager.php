<?php

namespace B;

require_once('DB.php');

class BookmarkManager
{
	protected $db;
	protected $user;
	protected $baseDir;

	public function __construct($user = false)
	{
		$config = require(__DIR__ . '/config.php');

		if (empty($config['baseDir'])) {
			throw new \Exception('baseDir not defined in config.php');
		}

		$this->baseDir = $config['baseDir'];

		if (!is_dir($this->baseDir)) {
			throw new \Exception('invalid baseDir in config.php');
		}
		
		if (!is_writeable($this->baseDir)) {
			throw new \Exception('baseDir in config.php not writeable');
		}

		$this->baseDir = rtrim($this->baseDir, '/') . '/';

		if (empty($user)) {
			if (empty($config['defaultUser'])) {
				throw new \Exception('no default user specified');
			}

			$user = $config['defaultUser'];
		}

		if (!is_dir($this->baseDir . $user)) {
			throw new \Exception('no such user db');
		}
		
		if (!is_writeable($this->baseDir . $user)) {
			throw new \Exception('user dir not writeable');
		}

		$this->user = $user;

		$this->db = new DB($this->baseDir . $user . '/b.db');
	}

	public function getDB()
	{
		return $this->db;
	}

	public function handleAjaxRequest($postData)
	{
		if (empty($postData['action'])) {
			return;
		}

		$action = $postData['action'];

		$error = true;
		$result = array();

		if (!empty($_POST['id'])) {
			$id = $_POST['id'];
			$result['id'] = $id;
		} else {
			$id = false;
		}

		try {
			switch ($action) {
				case 'add':
					list($url, $desc) = explode(' ', $postData['url'], 2);
					$this->addBookmark($url, $desc);
					$error = false;
					break;

				case 'delete':
					if ($id) {
						$this->db->deleteEntry($id);
						$error = false;
					}
					break;

				case 'settitle':
					if ($id && !empty($postData['title'])) {
						$this->db->setTitle($id, $postData['title']);
						$error = false;
						$result['title'] = $postData['title'];
					}
					break;

				case 'setlink':
					if ($id && !empty($postData['link'])) {
						$this->db->setLink($id, $postData['link']);
						$error = false;
						$result['link'] = $postData['link'];
					}
					break;

				default:
					return false;
			}
		} catch (\Exception $e) {
			$result['message'] = $e->getMessage();
		}

		if ($error) {
			$result['result'] = false;
		} else {
			$result['result'] = true;
		}

		echo json_encode($result, JSON_FORCE_OBJECT);

		exit();
	}

	public function addBookmark($url, $appendDesc = '')
	{
		$body = $this->fetch($url);
		$desc = $this->extractTitle($body) . ' ' . $appendDesc;

		return $this->db->add($desc, $url);
	}

	private function fetch($url)
	{
		if (($h = curl_init($url)) === false) {
			throw new \Exception('could not init curl');
		}

		curl_setopt($h, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($h, CURLOPT_FOLLOWLOCATION, true);

		if (($ret = curl_exec($h)) === false) {
			throw new \Exception('could not fetch');
		}

		return $ret;
	}

	private function extractTitle($body)
	{
		if (!preg_match('@<title>([^<]+)@', $body, $m)) {
			return '(unknown title)';
		}

		$ret = trim(html_entity_decode($m[1]));

		$enc = mb_detect_encoding($ret, 'UTF-8,ISO-8859-1', true);

		if ($enc !== 'UTF-8') {
			$ret = mb_convert_encoding($ret, 'UTF-8', $enc);
		}

		return $ret;
	}

}

