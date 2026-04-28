<?php
function handleMultiSelect()
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    include __DIR__ . '/../../pages/errors/404.php';
    return;
  }

  if (!checkAdmin()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    return;
  }

  $payload = json_decode(file_get_contents('php://input'), true);
  $data = $payload['data'] ?? null;

  if (!$data || !isset($data['type']) || !isset($data['ids']) || !is_array($data['ids'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    return;
  }

  $ids = array_values(array_filter(array_map('intval', $data['ids']), function ($id) {
    return $id > 0;
  }));

  if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No valid ids provided']);
    return;
  }

  $type = (string) $data['type'];
  $input = isset($data['input']) ? trim((string) $data['input']) : '';
  $values = isset($data['values']) && is_array($data['values'])
    ? array_values(array_filter(array_map(function ($value) {
      return trim((string) $value);
    }, $data['values'])))
    : [];

  $addValues = isset($data['add']) && is_array($data['add'])
    ? array_values(array_filter(array_map(function ($value) {
      return trim((string) $value);
    }, $data['add'])))
    : [];

  $removeValues = isset($data['remove']) && is_array($data['remove'])
    ? array_values(array_filter(array_map(function ($value) {
      return trim((string) $value);
    }, $data['remove'])))
    : [];

  $addValues = array_values(array_unique($addValues, SORT_STRING));
  $removeValues = array_values(array_unique($removeValues, SORT_STRING));

  if ($input !== '') {
    $values[] = $input;
  }

  $values = array_values(array_unique($values, SORT_STRING));

  if (in_array($type, ['groupAdd', 'groupDel', 'tagAdd', 'tagDel'], true) && empty($values)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Input is required for this action']);
    return;
  }

  if (in_array($type, ['tagSync', 'groupSync'], true) && empty($addValues) && empty($removeValues)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No changes provided for sync action']);
    return;
  }

  $pdo = connectToDatabase();

  try {
    $pdo->beginTransaction();

    switch ($type) {
      case 'delete':
        include __DIR__ . '/deleteVisitor.php';
        foreach ($ids as $id) {
          deleteVisitor($id);
        }
        break;
      case 'groupAdd':
        foreach ($ids as $id) {
          foreach ($values as $value) {
            addToVisitorGroup($id, $value);
          }
        }
        break;
      case 'groupSync':
        foreach ($ids as $id) {
          foreach ($addValues as $value) {
            addToVisitorGroup($id, $value);
          }
          foreach ($removeValues as $value) {
            removeVisitorGroup($id, $value);
          }
        }
        break;
      case 'tagAdd':
        foreach ($ids as $id) {
          foreach ($values as $value) {
            addToVisitorTag($id, $value);
          }
        }
        break;
      case 'tagSync':
        foreach ($ids as $id) {
          foreach ($addValues as $value) {
            addToVisitorTag($id, $value);
          }
          foreach ($removeValues as $value) {
            removeVisitorTag($id, $value);
          }
        }
        break;
      case 'groupDel':
        foreach ($ids as $id) {
          foreach ($values as $value) {
            removeVisitorGroup($id, $value);
          }
        }
        break;
      case 'tagDel':
        foreach ($ids as $id) {
          foreach ($values as $value) {
            removeVisitorTag($id, $value);
          }
        }
        break;
      default:
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unsupported action']);
        closeConnection($pdo);
        return;
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Multi-select action failed']);
  }

  closeConnection($pdo);
}
