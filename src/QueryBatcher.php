<?php

namespace Vanderbilt\PortProjectToRemoteREDCap\ExternalModule;

class QueryBatcher
{
	public const MSG_CSV_TOO_LARGE = "File size limit exceeded!";
	private ExternalModule $module;
	private string $input_query;
	private array $query_params;
	private int $nrow;
	private int $max_batch_size = 2 * 1024 * 1024;
	private int $n_batches;
	private int $rows_per_batch;
	private int $max_csv_bytes;

	public function __construct(
		ExternalModule $module,
		string $input_query,
		array $query_params = [],
		int $max_batch_size = null,
		int $max_csv_bytes = null
	) {
		$this->module = $module;
		$this->input_query = $input_query;
		$this->query_params = $query_params;

		$this->setMaxBatchSize($max_batch_size);
		$this->setMaxCsvSize($max_csv_bytes);

		$this->setRowCount();
	}


	public function setMaxBatchSize(int $bytes = null): void {
		// default 2GB
		$default = 2 * (1024 ** 3);
		$this->max_batch_size = ($bytes) ?? $default;
	}


	public function setMaxCsvSize(int $bytes = null) {
		// maxUploadSizeFileRepository defined in <rc_root>/Config/init_funcitons.php
		// system default is 128 MB, stored as 128
		$default = maxUploadSizeFileRepository() * (1024 ** 2);
		$this->max_csv_bytes = ($bytes) ?? $default;
	}


	private function setRowCount() {
		$row_count_sql = preg_replace("/SELECT .* FROM/", "SELECT COUNT(*) AS nrow FROM", $this->input_query, 1);
		$nrow = $this->module->queryWrapper($row_count_sql, $this->query_params)[0]['nrow'];
		$this->nrow = $nrow;
	}


	public function getRowCount() {
		return $this->nrow;
	}


	public function estimateQuerySize(int $sample_first = 5) {
		// estimate total query size by selecting first n rows
		// assuming this is a representative average, multply by total rows

		$start = memory_get_usage();

		$first_5 = $this->module->queryWrapper(
			$this->input_query . " LIMIT $sample_first",
			$this->query_params
		);

		$end = memory_get_usage();

		$size = ((abs($end - $start) / $sample_first) * $this->nrow);

		return $size;
	}

	public function estimateBatches() {
		$query_size = $this->estimateQuerySize();

		if ($query_size == 0 || $this->max_batch_size == 0) {
			$this->n_batches = 0;
			$this->rows_per_batch = 0;
		} else {
			$this->n_batches = ceil($query_size / $this->max_batch_size);
			$this->rows_per_batch = ceil($this->nrow / $this->n_batches);
		}

		return $this->n_batches;
	}


	public function queryBatch(int $batch_number) {
		$batch_start = ($batch_number - 1) * $this->rows_per_batch;

		$limits = " LIMIT {$this->rows_per_batch} OFFSET {$batch_start}";

		$batch_sql = $this->input_query . $limits;

		$batch_result = $this->module->queryWrapper($batch_sql, $this->query_params);

		return $batch_result;
	}

	public function portBatch($batch_result, string $file_name) {
		if ($this->nrow == 0) {
			// TODO: is a csv containing only a header useful to indicate a port was attempted?
			return;
		}

		try {
			$tmp_csv = $this->dumpTableToCSV($batch_result);
		} catch (\Exception $e) {
			if ($e->getMessage() !== self::MSG_CSV_TOO_LARGE) {
				throw $e;
			}
			if (count($batch_result) == 1) {
				throw new \Exception("Data are too large to enter a single row in {$file_name} CSV.");
			}
			// HACK: split array in half and try again in lieu of picking up where it left off
			$sub_batches = array_chunk($batch_result, ceil(count($batch_result) / 2));
			$this->portBatch($sub_batches[0], $file_name . ".1");
			$this->portBatch($sub_batches[1], $file_name . ".2");
			return;
		}

		$tmp_csv_path = stream_get_meta_data($tmp_csv)['uri'];

		if (!str_ends_with($file_name, ".csv")) {
			$file_name .= ".csv";
		}
		$cfile = curl_file_create($tmp_csv_path, 'text/csv', $file_name);

		$remote_folder_id = $this->module->getReservedFileRepoFolder();
		$post_params = [
			"content" => "fileRepository",
			"action" => "import",
			"file" => $cfile,
			"returnFormat" => "json",
			"folder_id" => $remote_folder_id
		];

		// NOTE: response for file import is just empty string on success
		// TODO: parse and deliver summary of files delivered to frontend
		$response = $this->module->curlPOST($post_params, true);

		fclose($tmp_csv);
	}


	/*
	 * @param array $arr Array of associative arrays in the format [["column_name" => "value"]]; passed by reference to save on memory
	 */
	public function dumpTableToCSV(array &$arr) {
		$filesize_limit = $this->max_csv_bytes;
		$tmp = tmpfile();
		$tmp_path = stream_get_meta_data($tmp)['uri'];

		if (!($arr)) {
			return;
		}

		// dump header row
		fputcsv($tmp, array_keys($arr[0]));

		foreach ($arr as $row) {

			if ($filesize_limit > 0) {
				$cur_size = filesize($tmp_path);

				$tmp2 = tmpfile();
				$tmp2_path = stream_get_meta_data($tmp2)['uri'];
				fputcsv($tmp2, $row);
				$predicted_size = filesize($tmp2_path) + $cur_size;
				fclose($tmp2);

				if ($predicted_size >= $filesize_limit) {
					// NOTE: a more elegant solution would be to return tmp as well as the idx of the row that was too big
					fclose($tmp);
					throw new \Exception(self::MSG_CSV_TOO_LARGE);
				}
			}

			fputcsv($tmp, $row);
		}
		return $tmp;
	}


	public function portAllBatches(string $file_prefix) {
		$batches = $this->estimateBatches();
		for ($i = 1; $i < $batches + 1; ++$i) {
			$batch_result = $this->queryBatch($i);

			$file_name = "{$file_prefix}";
			if ($batches > 1) {
				$file_name .= "_{$i}";
			}

			$this->portBatch($batch_result, $file_name);
		}
	}
}
