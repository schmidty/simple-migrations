<?php

class Migration
{
	private
		$admin_email = 'adminuser@gmail.com',

		$db_user = NULL,

		$db_password = NULL;

	protected
		$execute_query = TRUE,

		$folders = array('schema', 'data'),

		$connection_string = NULL,

		$tables = array(),

		$file_error = NULL,

		$folder = NULL,

		$connection = NULL,

		$new_files = NULL;


	public function __construct($connection_string, $db_user,  $db_password)
	{
		$this->db_user = $db_user;
		$this->db_password = $db_password;
		$this->connection_string = $connection_string;

		$this->getConnection();
		$this->execute();
	}

	protected function getConnection()
	{
		if (!is_null($this->connection_string) && !is_null($this->db_user) && !is_null($this->db_password))
			$this->connection = new PDO($this->connection_string, $this->db_user, $this->db_password);
	}

	protected function execute()
	{
		$new_files = NULL;

		foreach($this->folders as $folder)
		{
			// Check retrys
			$this->retryRunOnResult($folder);

			// Process
			$this->folder = $folder;
			$path = "migrations/{$folder}/";
			$files = $this->checkForNewFiles($path);
			$results = $this->checkMigrationsTable($folder);
			$new_files = $this->compareFilesAndResults($files, $results); // Get all successful files
			$this->processNewFiles($new_files, $path);
		}
	}

	protected function retryRunOnResult($folder)
	{
		$connection = $this->connection;
		$path = "migrations/{$folder}/";

		$sql = "SELECT `id`, `file`, `retrys` FROM `migrations` WHERE `folder` = '{$folder}' AND `success` = 0";
		if ( !($stmt = $connection->query($sql)) ) die("ERROR: Could not SELECT line from migrations table. Exiting...\n". $sql ."\n");
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach($results as $count => $val)
		{
			$results = $stmt = $id = NULL;
			$retry_count = 0;
			
			$file = $val['file'];
			$id = $val['id'];
			$retry_count = $val['retrys'];

			$this->file_error = NULL;
			$filepath = $path . $file;
			
			if ($retry_count == 10)
			{
				$this->alertUserToFailedSQL($id, $file, $folder);
				break;
			}

			$file_string = file_get_contents($filepath);
			$lines = preg_split("/;/", $file_string, NULL, PREG_SPLIT_NO_EMPTY);

			$this->processSQLStatment($lines, $path);
			
			// Retry count
			if (!$this->file_error)
			{
				echo "Retried file '{$file}' and SUCCESS\n";
				$sql = "UPDATE `migrations` SET `success`= 1, `updated` = NOW() WHERE id = {$id} ";
			}
			else
			{
				echo "Retried file '{$file}' and failed...\n";
				$retry_count++;
				$sql = "UPDATE `migrations` SET `success`= 0, `updated` = NOW(), `retrys` = {$retry_count} WHERE id = {$id} ";
			}
			if ( !($stmt = $connection->query($sql)) )
				die("ERROR: Could not UPDATE row from migrations table. Exiting...\n". $sql ."\n");
			else
				$this->file_error = FALSE;
		}
	}

	protected function processNewFiles($files, $path)
	{
		$connection = $this->connection;

		foreach($files as $file)
		{
			$folder = $this->folder;
			$filepath = $path . $file;
			$file_string = file_get_contents($filepath);
			$lines = preg_split("/;/", $file_string, NULL, PREG_SPLIT_NO_EMPTY);

			$this->processSQLStatment($lines, $path);

			$table_string = join(', ', $this->tables);

			if (!is_null($this->file_error))
			{
				$id = NULL;

				// failure
				if ( !($results = $this->checkExistingRow($folder, $file)) )
				{
					$sql = "INSERT INTO `migrations`(`file`, `folder`, `success`, `error_message`, `created`) VALUES ('{$file}', '{$folder}', 0, 'ERROR LINE: {$this->file_error}', NOW())";
				}
				else
				{
					$id = $results['id'];
					$sql = "UPDATE `migrations` SET `success`= 0, `error_message` =  'ERROR LINE: {$this->file_error}', `updated` = NOW() WHERE id = {$id} ";
				}
				if (!$connection->query($sql)) die("ERROR: Could not INSERT line result for ERROR into migrations table. Exiting...\n". $sql ."\n");
				$this->file_error = NULL;
				$results = NULL;

			}
			else
			{
				// success
				if (!$this->checkExistingRow($folder, $file))
				{
					$sql = "INSERT INTO migrations(`file`, `table`, `folder`, `success`, `created`) VALUES ('{$file}', '{$table_string}', '{$folder}', 1, NOW())";
					if (!$connection->query($sql)) die("ERROR: Could not INSERT line result for SUCCESS into migrations table. Exiting...\n". $sql ."\n");
				}
			}
		}
	}

	protected function checkExistingRow($folder, $file)
	{
		$connection = $this->connection;

		$sql = "SELECT `id` FROM `migrations` WHERE `folder` = '{$folder}' AND `file` = '{$file}' ";
		if ( !($stmt = $connection->query($sql)) ) die("ERROR: Could not INSERT line result for SUCCESS into migrations table. Exiting...\n". $sql ."\n");
		
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	protected function processSQLStatment($lines, $path)
	{
		$connection = $this->connection;
		$count = 0;

		foreach($lines as $line)
		{
			$count++;
			$line = trim($line);

			if (!empty($line) && !preg_match('/-- /', $line))
			{
				$matches = '';
				$line .= ";";

				echo "[$count]\t{$line}\n";

				if (strpos($path, 'schema') !== FALSE)
					preg_match('/TABLE.*`(.*?)`/', $line, $matches);

				if (strpos($path, 'table') !== FALSE)
					preg_match('/INTO `(.*?)`/', $line, $matches);

				if (isset($matches[1]))
				{
					$table = trim($matches[1]);

					if (!in_array($table, $this->tables))
						$this->tables[] = $table;
				}

				if ($this->execute_query)
				{

					$result = $connection->query($line);

					if ($result)
					{
						echo ">> Success\n";
					}
					else
					{
						echo ">> ERROR: ". $line ."\n";
						$this->file_error = $count;
					}
				}
			}
		}
	}

	protected function compareFilesAndResults($files, $results)
	{
		if (count($results) == 0)
			return $files;

		$results_files = array();

		foreach($results as $count => $key)
		{
			if (!in_array($key['file'], $files))
				$results_files[] = $key['file'];
		}

		return $results_files;
	}

	protected function checkMigrationsTable($folder, $success = NULL)
	{
		$results = NULL;
		$connection = $this->connection;

		$sql = '
	SELECT * FROM `migrations`
	WHERE `folder` = "'. $folder .'" ';

		if (!is_null($success))
		{
			$sql .= "AND `success` = {$success} ";
		}

		$statement = $connection->query($sql);

		if ($statement)
			$results = $statement->fetchAll(PDO::FETCH_ASSOC);

		return $results;
	}

	protected function checkForNewFiles($filepath)
	{
		$files = scandir($filepath);

		unset($files[array_search('.', $files)]);
		unset($files[array_search('..', $files)]);

		foreach($files as $file)
		{
			$file = trim($file);
			preg_match('/^.*\.sw.$/', $file, $matches);

			if (count($matches))
				unset($files[array_search($matches[0], $files)]);
		}

		return array_values($files);
	}

	protected function alertUserToFailedSQL($id, $file, $folder)
	{
		echo "{$id} - '{$folder}/{$file}' has failed 10 attempts to be executed... please check this file\n";
	}
}
