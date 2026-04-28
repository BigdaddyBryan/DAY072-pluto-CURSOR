<?php

function deactivateOneTime($token = null)
{
	// Only admins can deactivate tokens
	if (!checkAdmin()) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'Unauthorized']);
		exit;
	}

	// Get token from multiple sources
	if (!$token) {
		$jsonData = json_decode(file_get_contents('php://input'), true);
		$token = $_GET['token'] ?? $_POST['token'] ?? ($jsonData['token'] ?? null);
	}

	if (!$token || empty($token)) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'Token required']);
		exit;
	}

	$token = trim($token);

	try {
		$filePath = __DIR__ . '/../../public/json/allowedTokens.json';

		if (!file_exists($filePath)) {
			throw new Exception('Tokens file not found');
		}

		$fileContents = file_get_contents($filePath);
		$allowedTokens = json_decode($fileContents, true);

		if (!is_array($allowedTokens) || !isset($allowedTokens['tokens'])) {
			throw new Exception('Invalid tokens file');
		}

		if (!array_key_exists($token, $allowedTokens['tokens'])) {
			http_response_code(404);
			echo json_encode(['success' => false, 'message' => 'Token not found']);
			exit;
		}

		if (!empty($allowedTokens['tokens'][$token]['deactivated'])) {
			echo json_encode(['success' => true, 'message' => 'Already deactivated']);
			exit;
		}

		$allowedTokens['tokens'][$token]['deactivated'] = true;
		$allowedTokens['tokens'][$token]['deactivated_on'] = time();

		if (!isset($allowedTokens['history_events']) || !is_array($allowedTokens['history_events'])) {
			$allowedTokens['history_events'] = [];
		}
		$allowedTokens['history_events'][] = [
			'event' => 'deactivated',
			'token' => $token,
			'at' => time(),
			'actor' => (string) ($_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown')
		];

		$written = file_put_contents($filePath, json_encode($allowedTokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		if ($written === false) {
			throw new Exception('Failed to save');
		}

		echo json_encode(['success' => true, 'message' => 'Link deactivated']);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
	}

	exit;
}

function deactivateAllOneTimes($includeUsed = false)
{
	// Only admins can deactivate tokens
	if (!checkAdmin()) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'Unauthorized']);
		exit;
	}

	try {
		$filePath = __DIR__ . '/../../public/json/allowedTokens.json';

		if (!file_exists($filePath)) {
			throw new Exception('Tokens file not found');
		}

		$fileContents = file_get_contents($filePath);
		$allowedTokens = json_decode($fileContents, true);

		if (!is_array($allowedTokens) || !isset($allowedTokens['tokens'])) {
			throw new Exception('Invalid tokens file');
		}

		$deactivatedCount = 0;
		$now = time();
		if (!isset($allowedTokens['history_events']) || !is_array($allowedTokens['history_events'])) {
			$allowedTokens['history_events'] = [];
		}

		foreach ($allowedTokens['tokens'] as $token => &$tokenData) {
			if (!empty($tokenData['deactivated']) || (!$includeUsed && !empty($tokenData['used']))) {
				continue;
			}

			$tokenData['deactivated'] = true;
			$tokenData['deactivated_on'] = $now;
			$allowedTokens['history_events'][] = [
				'event' => 'deactivated',
				'token' => (string) $token,
				'at' => $now,
				'actor' => (string) ($_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown')
			];
			$deactivatedCount++;
		}
		unset($tokenData);

		$written = file_put_contents($filePath, json_encode($allowedTokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		if ($written === false) {
			throw new Exception('Failed to save');
		}

		echo json_encode([
			'success' => true,
			'message' => $deactivatedCount . ' link(s) deactivated',
			'count' => $deactivatedCount
		]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
	}

	exit;
}
