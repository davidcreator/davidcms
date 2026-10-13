<?php
namespace Reamur\System\Library\DB;

/**
* NoSQL adapter (MongoDB)
* This adapter uses the official MongoDB driver for PHP.
* The interface follows the same basic contract as other database classes:
* query(), escape(), countAffected(), getLastId(), isConnected().
* Expected format of the command passed to query():
* - JSON string with the following minimal structure:
* {
* "collection": "collection_name",
* "action": "find" | "insertOne" | "insertMany" | "updateOne" | "updateMany" | "deleteOne" | "deleteMany" | "aggregate",
* "filter": { ... }, // optional (find/update/delete)
* "options": { ... }, // optional (find/update/delete)
* "document": { ... }, // required for insertOne
* "documents": [ ... ], // required for insertMany
* "update": { ... }, // required for update*
* "pipeline": [ ... ] // required for aggregation
* }
 *
 * Exemplo de uso:
 *   $json = json_encode([
 *     'collection' => 'users',
 *     'action' => 'find',
 *     'filter' => ['status' => 'active'],
 *     'options' => ['limit' => 10]
 *   ]);
 *   $result = $db->query($json);
 */
class Nosql {
	/**
	 * @var \MongoDB\Client|null
	 */
	private ?\MongoDB\Client $client = null;

	/**
	 * @var \MongoDB\Database|null
	 */
	private ?\MongoDB\Database $database = null;

	/**
	 * @var int
	 */
	private int $affected = 0;

	/**
	 * @var string|null
	 */
	private ?string $lastId = null;

	/**
	 * Constructor
	 *
	 * @param string $hostname
	 * @param string $username
	 * @param string $password
	 * @param string $database
	 * @param string $port
	 * @param array  $options   Extra options for MongoDB\Client
	 */
	public function __construct(
		string $hostname,
		string $username,
		string $password,
		string $database,
		string $port = '27017',
		array $options = []
	) {
		if (!class_exists(\MongoDB\Client::class)) {
			throw new \Exception('MongoDB extension/driver not found. Install mongodb/mongodb via Composer and enable the mongodb PHP extension.');
		}

		$port = $port ?: '27017';

		$uriOptions = [
			'username' => $username,
			'password' => $password
		];

		$driverOptions = $options['driverOptions'] ?? [];
		unset($options['driverOptions']);

		$uri = "mongodb://{$hostname}:{$port}";

		try {
			$this->client = new \MongoDB\Client($uri, $options + $uriOptions, $driverOptions);
			$this->database = $this->client->selectDatabase($database);
		} catch (\Throwable $e) {
			throw new \Exception('Error: Could not connect to MongoDB at ' . $hostname . ':' . $port . '<br/>Message: ' . $e->getMessage());
		}
	}

	/**
	 * Execute a NoSQL command expressed as JSON
	 *
	 * @param string $commandJson JSON string describing the operation
	 *
	 * @return bool|object
	 */
	public function query(string $commandJson): bool|object {
		$this->ensureConnection();

		$payload = json_decode($commandJson, true);
		if (!is_array($payload)) {
			throw new \Exception('Invalid command. Expected JSON string describing the NoSQL operation.');
		}

		$collectionName = $payload['collection'] ?? null;
		$action = strtolower($payload['action'] ?? 'find');
		$filter = $this->convertObjectIds($payload['filter'] ?? []);
		$options = $payload['options'] ?? [];

		if (!$collectionName) {
			throw new \Exception('No collection provided for NoSQL operation.');
		}

		$collection = $this->database->selectCollection($collectionName);
		$this->affected = 0;
		$this->lastId = null;

		try {
			switch ($action) {
				case 'find':
					$cursor = $collection->find($filter, $options);
					$data = $this->normalizeDocuments(iterator_to_array($cursor, false));
					$this->affected = count($data);
					return $this->buildResult($data);

				case 'aggregate':
					$pipeline = $payload['pipeline'] ?? [];
					$cursor = $collection->aggregate($pipeline, $options);
					$data = $this->normalizeDocuments(iterator_to_array($cursor, false));
					$this->affected = count($data);
					return $this->buildResult($data);

				case 'insertone':
					$document = $payload['document'] ?? [];
					$result = $collection->insertOne($document, $options);
					$this->affected = $result->getInsertedCount();
					$this->lastId = (string)$result->getInsertedId();
					return true;

				case 'insertmany':
					$documents = $payload['documents'] ?? [];
					$result = $collection->insertMany($documents, $options);
					$this->affected = $result->getInsertedCount();
					$ids = $result->getInsertedIds();
					$this->lastId = $ids ? (string)end($ids) : null;
					return true;

				case 'updateone':
					$update = $payload['update'] ?? [];
					$result = $collection->updateOne($filter, $update, $options);
					$this->affected = $result->getModifiedCount();
					return true;

				case 'updatemany':
					$update = $payload['update'] ?? [];
					$result = $collection->updateMany($filter, $update, $options);
					$this->affected = $result->getModifiedCount();
					return true;

				case 'deleteone':
					$result = $collection->deleteOne($filter, $options);
					$this->affected = $result->getDeletedCount();
					return true;

				case 'deletemany':
					$result = $collection->deleteMany($filter, $options);
					$this->affected = $result->getDeletedCount();
					return true;

				default:
					throw new \Exception('Unsupported NoSQL action: ' . $action);
			}
		} catch (\Throwable $e) {
			throw new \Exception('Error: ' . $e->getMessage() . '<br/>' . $commandJson);
		}
	}

	/**
	 * Escape string (simple addslashes fallback for symmetry)
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public function escape(string $value): string {
		return addslashes($value);
	}

	/**
	 * Number of documents affected by last write operation
	 *
	 * @return int
	 */
	public function countAffected(): int {
		return $this->affected;
	}

	/**
	 * Last inserted ID (if available)
	 *
	 * @return int
	 */
	public function getLastId(): int {
		return (int)($this->lastId ?? 0);
	}

	/**
	 * Last inserted ObjectId as string (MongoDB specific)
	 *
	 * @return string|null
	 */
	public function getLastObjectId(): ?string {
		return $this->lastId;
	}

	/**
	 * Is connection alive
	 *
	 * @return bool
	 */
	public function isConnected(): bool {
		return $this->client !== null && $this->database !== null;
	}

	/**
	 * Close connection
	 *
	 * @return bool
	 */
	public function close(): bool {
		$this->client = null;
		$this->database = null;
		return true;
	}

	/**
	 * Optional prepare wrapper (for interface parity)
	 *
	 * @param string $commandJson
	 * @param array  $params Not used; kept for signature compatibility
	 *
	 * @return bool|object
	 */
	public function prepare(string $commandJson, array $params = []): bool|object {
		return $this->query($commandJson);
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->close();
	}

	/**
	 * Ensure connection is available
	 */
	private function ensureConnection(): void {
		if (!$this->isConnected()) {
			throw new \Exception('Database connection is not available');
		}
	}

	/**
	 * Build result object similar to SQL adapters
	 *
	 * @param array $data
	 * @return object
	 */
	private function buildResult(array $data): object {
		$result = new \stdClass();
		$result->num_rows = count($data);
		$result->row = $data[0] ?? [];
		$result->rows = $data;
		return $result;
	}

	/**
	 * Convert string ObjectIds in filters to BSON ObjectId
	 *
	 * @param array $filter
	 * @return array
	 */
	private function convertObjectIds(array $filter): array {
		foreach ($filter as $key => $value) {
			if ($key === '_id' && is_string($value) && preg_match('/^[a-fA-F0-9]{24}$/', $value)) {
				$filter[$key] = new \MongoDB\BSON\ObjectId($value);
			} elseif (is_array($value)) {
				$filter[$key] = $this->convertObjectIds($value);
			}
		}
		return $filter;
	}

	/**
	 * Normalize documents converting ObjectId to string
	 *
	 * @param array $documents
	 * @return array
	 */
	private function normalizeDocuments(array $documents): array {
		foreach ($documents as &$doc) {
			if (isset($doc['_id']) && $doc['_id'] instanceof \MongoDB\BSON\ObjectId) {
				$doc['_id'] = (string)$doc['_id'];
			}
		}
		return $documents;
	}
}
