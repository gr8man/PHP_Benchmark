<?php

/**
 * Advanced PHP Benchmark Script
 * Author: Bogdan Lambarski : https://github.com/gr8man/
 * Requirements: PHP 7.0+
 */

declare(strict_types=1);

if (version_compare(PHP_VERSION, "7.0.0", "<")) {
    exit("This script requires PHP 7.0 or higher.\n");
}

class PhpBenchmark
{
    private $startTime;
    private $startMemory;
    private $tests = [];
    public $results = [];
    public $results_all = [];
    private $isCli;
    private $memory_limit;

    public function __construct()
    {
        $this->isCli = php_sapi_name() === "cli";
        $this->memory_limit = ini_get("memory_limit");
    }

    public function addTest(string $name, int $count, callable $function)
    {
        $this->tests[] = [
            "name" => $name,
            "count" => $count,
            "func" => $function,
        ];
    }

    public function runTests()
    {
        ob_start();
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        foreach ($this->tests as $test) {
            // Wymuszenie odśmiecania pamięci przed i po teście
            gc_collect_cycles();

            $startMem = memory_get_usage();
            $start = microtime(true);

            // Run the actual test
            $resultBuffer = $test["func"]($test["count"]);

            // Usunięcie bufora, aby zwolnić pamięć
            unset($resultBuffer);

            $this->results[] = [
                "name" => $test["name"],
                "time" => $this->formatTime(microtime(true) - $start),
                "memory" => $this->formatSize(
                    max(0, memory_get_usage() - $startMem)
                ),
            ];

            gc_collect_cycles();
        }

        $this->results_all = [
            "time" => $this->formatTime(microtime(true) - $this->startTime),
            "memory" => $this->formatSize(memory_get_peak_usage(true)),
            "bufferedContent" => ob_get_clean(),
        ];
    }

    private function formatSize($bytes)
    {
        if ($bytes == 0) {
            return "0 B";
        }
        if ($bytes < 1024) {
            return $bytes . " B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . " KB";
        }
        return round($bytes / 1048576, 2) . " MB";
    }

    private function formatTime($seconds)
    {
        return number_format($seconds, 4) . " s";
    }

    public function printResult()
    {
        // Set headers for web browsers if not already sent
        if (!headers_sent() && !$this->isCli) {
            header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
            header("Cache-Control: no-cache, must-revalidate, max-age=0");
        }

        // Gather system information for display
        $info = [
            "PHP" => PHP_VERSION,
            "OS" => php_uname("s") . " " . php_uname("r") . " (" . php_uname("m") . ")",
            "SAPI" => php_sapi_name(),
            "Server" => $_SERVER["SERVER_SOFTWARE"] ?? "CLI",
            "Mem Limit" => ini_get("memory_limit"),
            "Time Limit" => ini_get("max_execution_time") . "s",
            "OPCache" => (function_exists("opcache_get_status") && is_array(opcache_get_status()) && @opcache_get_status()["opcache_enabled"]) ? "On" : "Off",
            "JIT" => (function_exists("opcache_get_status") && is_array(opcache_get_status()) && !empty(opcache_get_status()["jit"]["enabled"])) ? "On" : "Off",
        ];

        // Main container - minimalist light design
        echo '<div style="font-family: ui-monospace, monospace; max-width: 900px; margin: 10px auto; color: #333; background: #fff; padding: 15px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd; font-size: 13px; line-height: 1.3;">';

        // Header bar with version and repository link
        echo '<div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #f8f9fa; border-radius: 6px; font-weight: bold; border: 1px solid #e9ecef; margin-bottom: 20px; font-size: 16px;">';
        echo '<div style="display: flex; flex-direction: column;">';
        echo '<span style="color: #2c3e50; font-size: 20px; text-transform: uppercase;">PHP Benchmark <span style="font-size: 14px; color: #7f8c8d; text-transform: none;">v1.0</span></span>';
        echo '<a href="https://github.com/gr8man/PHP_Benchmark" target="_blank" style="font-size: 11px; color: #3498db; text-decoration: none; font-weight: normal; margin-top: 4px;">GitHub: gr8man</a>';
        echo '</div>';
        echo "<span>Total Time: <span style='color: #d35400;'>{$this->results_all["time"]}</span> | Peak Mem: <span style='color: #27ae60;'>{$this->results_all["memory"]}</span></span>";
        echo '</div>';

        echo '<div style="display: flex; gap: 20px; align-items: flex-start;">';

        // Left Column: System Info with row separators
        echo '<div style="flex: 1; min-width: 260px;">';
        echo '<h4 style="margin: 0 0 10px; color: #2980b9; border-bottom: 2px solid #ecf0f1; padding-bottom: 6px; font-size: 14px;">System Info</h4>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($info as $key => $val) {
            echo "<tr><td style='padding: 6px 0; color: #555; font-weight: 600; border-bottom: 1px solid #f1f1f1;'>{$key}</td><td style='padding: 6px 0; text-align: right; color: #333; border-bottom: 1px solid #f1f1f1;'>{$val}</td></tr>";
        }
        echo '</table>';
        echo '</div>';

        // Right Column: Benchmark Results table
        echo '<div style="flex: 2;">';
        echo '<h4 style="margin: 0 0 10px; color: #2980b9; border-bottom: 2px solid #ecf0f1; padding-bottom: 6px; font-size: 14px;">Test Results</h4>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo "<tr><th style='text-align: left; padding: 6px 0; color: #2c3e50; border-bottom: 1px solid #bdc3c7;'>Test Name</th><th style='text-align: right; color: #2c3e50; border-bottom: 1px solid #bdc3c7;'>Time</th></tr>";
        foreach ($this->results as $result) {
            echo "<tr>";
            echo "<td style='padding: 6px 0; border-bottom: 1px solid #f1f1f1; color: #34495e;'>{$result["name"]}</td>";
            echo "<td style='text-align: right; color: #d35400; border-bottom: 1px solid #f1f1f1; font-weight: 500;'>{$result["time"]}</td>";
            echo "</tr>";
        }
        echo '</table>';
        echo '</div>';

        echo '</div></div>';
    }
}

$benchmark = new PhpBenchmark();

// Zmniejszono delikatnie limity, aby wasm nie przepełniał pamięci
$benchmark->addTest("Math (Trigonometry & Powers)", 200000, function ($limit) {
    $r = 0;
    for ($i = 0; $i < $limit; $i++) {
        $a = $i * 2 + $i;
        $b = $i % 7;
        $r = sin($i) * cos($i);
        $t = tan($i);
        atan($i);
        $p = pow($i >> 2, 2);
        $s = sqrt($i);
        hypot($i, $b);
        $l = log($i + 1);
        exp($b);
        abs($i - $limit);
        ceil($i / 3.14);
        floor($i / 3.14);
        round($i / 3.14);
        is_finite($i);
        is_nan($i);
        pi();
    }
    return $r;
});

$benchmark->addTest("Heavy Geometry (Mesh Projections)", 20000, function ($limit) {
    $mesh = [];
    for ($v = 0; $v < 20; $v++) {
        $mesh[] = ["x" => sin($v), "y" => cos($v), "z" => $v * 0.1];
    }

    $accumulatedArea = 0.0;

    for ($i = 0; $i < $limit; $i++) {
        $angle = $i * 0.0001;
        $cosA = cos($angle);
        $sinA = sin($angle);

        $minX = 9999.0;
        $maxX = -9999.0;
        $minY = 9999.0;
        $maxY = -9999.0;

        foreach ($mesh as &$vertex) {
            $rx = $vertex["x"] * $cosA - $vertex["z"] * $sinA;
            $rz = $vertex["x"] * $sinA + $vertex["z"] * $cosA;
            $ry = $vertex["y"] * $cosA - $rz * $sinA;
            $rz2 = $vertex["y"] * $sinA + $rz * $cosA;

            $vertex["x"] = $rx;
            $vertex["y"] = $ry;
            $vertex["z"] = $rz2;

            $zPerspective = $rz2 + 5.0;
            $px = $rx / $zPerspective;
            $py = $ry / $zPerspective;

            if ($px < $minX) $minX = $px;
            if ($px > $maxX) $maxX = $px;
            if ($py < $minY) $minY = $py;
            if ($py > $maxY) $maxY = $py;
        }

        $accumulatedArea += ($maxX - $minX) * ($maxY - $minY);
    }

    return $accumulatedArea;
});

$benchmark->addTest("String (Manipulation & Regex)", 20000, function ($limit) {
    $string = "The quick brown fox jumps over the lazy dog";
    $s = "";
    for ($i = 0; $i < $limit; $i++) {
        $s = str_shuffle($string);
        $u = strtoupper($s);
        $r = strrev($u);

        strpos($string, "fox");
        substr($string, 5, 10);

        str_replace(" ", "", $string);
        str_pad($string, 60, ".", STR_PAD_BOTH);
        trim("  " . $string . "  ");

        $parts = explode(" ", $string);
        implode("-", $parts);

        md5($r);
        base64_encode($string);

        preg_match("/(quick|lazy)/", $string);
        preg_replace("/[aeiou]/i", "*", $string);
        preg_split("/\s+/", $string);
    }
    return $s;
});

$benchmark->addTest("Loops & Logic (Heavy Branching)", 50000, function ($limit) {
    $x = 1;
    $y = 0;

    for ($i = 1; $i <= $limit; ++$i) {
        for ($j = 0; $j < 25; ++$j) {
            if (($i % 2 === 0 && $x % 3 !== 0) || $j % 7 === 0) {
                $x ^= $j << 1;
            } elseif ($j % 5 === 0 xor $x > 10000) {
                $x = $x * 3 + 1;
            } else {
                $x += $i >> 2;
            }

            switch (abs($x ^ $j) % 5) {
                case 0:
                    $y += $i;
                    break;
                case 1:
                case 2:
                    $y -= $j * 0.5;
                    break;
                case 3:
                    $x = ~$x;
                    break;
                default:
                    $y ^= $x;
            }

            if ($x > 1000000 || $x < -1000000) {
                $x = $x % 10000;
            }
        }
    }

    return $x + $y;
});

// Zmniejszono ze 1.500.000 do 200.000 (wasm ma mało RAM)
$benchmark->addTest("Object (Instantiation & Magic Methods)", 600000, function ($limit) {
    $checksum = 0;

    for ($i = 0; $i < $limit; $i++) {
        $obj = new class ($i) {
            private $id;
            public $val;

            public function __construct(int $id)
            {
                $this->id = $id;
                $this->val = "test";
            }

            public function __get(string $name)
            {
                return $this->id * 2;
            }

            public function __set(string $name, $value)
            {
                $this->val = (string) $value;
            }
        };

        $obj->dynamicProp = $i;
        $cloned = clone $obj;
        $checksum += $cloned->hiddenProp;

        // Szybkie zwolnienie z pamięci
        unset($obj, $cloned);
    }

    return $checksum;
});

$benchmark->addTest("Arrays (Creation & Sorting)", 10000, function ($limit) {
    $a = [];
    $sum = 0;
    for ($i = 0; $i < $limit; $i++) {
        $a = range(0, 200);
        shuffle($a);
        sort($a);
        $a = array_flip($a);
        $sum += array_sum($a);
        unset($a);
    }
    return $sum;
});

$benchmark->addTest("Data Processing (Filter & Sort)", 10000, function ($limit) {
    $data = [];
    for ($k = 0; $k < 100; $k++) {
        $data[] = ["id" => $k, "score" => $k * rand(1, 10), "cat" => $k % 5];
    }

    $res = [];
    for ($i = 0; $i < $limit; $i++) {
        $temp = array_filter($data, function ($row) {
            return $row["score"] > 200 && $row["cat"] % 2 === 0;
        });
        usort($temp, function ($a, $b) {
            return $b["score"] <=> $a["score"];
        });
        $res = $temp;
        unset($temp);
    }
    return $res;
});

$benchmark->addTest("Recursion (Heavy Call Stack)", 1000000, function ($limit) {
    $result = 0;

    $heavyRecurse = function ($depth, $x, $y) use (&$heavyRecurse) {
        if ($depth <= 0) {
            return $x + $y;
        }

        return $heavyRecurse($depth - 1, $x + 1, $y) +
            $heavyRecurse($depth - 1, $x, $y + 1);
    };

    $depth = 5;
    $callsPerLoop = 1 << $depth;
    $loops = intdiv($limit, $callsPerLoop);

    for ($i = 0; $i < $loops; $i++) {
        $result += $heavyRecurse($depth, $i, 1);
    }

    return $result;
});

$benchmark->addTest("Hashing (Crypto & Bcrypt)", 20000, function ($limit) {
    $h = "";
    $useHash = function_exists("hash");

    for ($i = 0; $i < $limit; $i++) {
        if ($useHash) {
            $h = hash("sha256", "string to hash " . $i);
        } else {
            $h = sha1("string to hash " . $i);
        }

        if ($i % 500 === 0) {
            password_hash("password", PASSWORD_DEFAULT, ["cost" => 4]);
        }
    }
    return $h;
});

$benchmark->addTest("JSON & Serialization", 100000, function ($limit) {
    $data = ["test" => 123, "array" => [1, 2, 3], "text" => "lorem ipsum"];
    $s = "";
    for ($i = 0; $i < $limit; $i++) {
        $j = json_encode($data);
        json_decode($j);
        $s = serialize($data);
        unserialize($s);
    }
    return $s;
});

$benchmark->addTest("Rand::rand (Basic)", 100000, function ($limit) {
    $x = 0;
    for ($i = 0; $i < $limit; $i++) {
        $x = rand(0, 1000000);
    }
    return $x;
});

$benchmark->addTest("Rand::mt_rand (Mersenne)", 100000, function ($limit) {
    $x = 0;
    for ($i = 0; $i < $limit; $i++) {
        $x = mt_rand(0, 1000000);
    }
    return $x;
});

$benchmark->addTest("Rand::random_int (CSPRNG)", 100000, function ($limit) {
    $x = 0;
    for ($i = 0; $i < $limit; $i++) {
        $x = random_int(0, 1000000);
    }
    return $x;
});

$benchmark->addTest("Rand::random_bytes", 100000, function ($limit) {
    $x = "";
    for ($i = 0; $i < $limit; $i++) {
        $x = random_bytes(32);
    }
    return $x;
});

$benchmark->addTest('IO::File Write', 10000, function ($limit) {
    $tmpFile = sys_get_temp_dir() . '/php_bench_io_test.txt';
    $data = str_repeat('1234567890', 500);
    if (file_exists($tmpFile)) unlink($tmpFile);

    for ($i = 0; $i < $limit; $i++) {
        file_put_contents($tmpFile, $data, FILE_APPEND);
    }
    $size = file_exists($tmpFile) ? filesize($tmpFile) : 0;
    if (file_exists($tmpFile)) unlink($tmpFile);
    return $size;
});

$benchmark->addTest('IO::File Read', 10000, function ($limit) {
    $tmpFile = sys_get_temp_dir() . '/php_bench_io_read.txt';
    $data = str_repeat('1234567890', 500);
    file_put_contents($tmpFile, str_repeat($data, 20));

    $content = '';
    for ($i = 0; $i < $limit; $i++) {
        $content = file_get_contents($tmpFile);
    }
    if (file_exists($tmpFile)) unlink($tmpFile);
    return $content;
});

$benchmark->addTest('Memory (Allocation & GC)', 50000, function ($limit) {
    $data = [];

    // Pobieramy bazowe zużycie dla tego konkretnego testu
    $startMem = memory_get_usage();

    // Bezpieczny limit WZGLĘDNY dla Wasm (pozwalamy zużyć DODATKOWE 5MB ponad to co już jest)
    $maxRelativeAllocation = 30 * 1024 * 1024;

    $actualLimit = 0;
    for ($i = 0; $i < $limit; $i++) {
        if (memory_get_usage() - $startMem > $maxRelativeAllocation) break;
        $data[] = str_repeat(chr(65 + ($i % 26)), 100) . $i;
        $actualLimit++;
    }

    for ($i = 0; $i < $actualLimit; $i += 2) {
        unset($data[$i]);
    }

    for ($i = 0; $i < $actualLimit; $i += 2) {
        if (memory_get_usage() - $startMem > $maxRelativeAllocation) break;
        $data[$i] = [
            'id' => $i,
            'token' => md5((string)$i),
            'active' => true
        ];
    }

    return $data;
});

$benchmark->runTests();
$benchmark->printResult();
