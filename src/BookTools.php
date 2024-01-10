<?php

/**
 * @method static error(string $string)
 * @method static info(string $string)
 * @method static debug(false|string $file)
 * @method static line(string $string)
 */
class BookTools
{
    /**
     * ファイル名変更
     */
    public static function converOutputZipFilename($filename)
    {
        $host = static::getSetting('DB_HOST');
        $db = static::getSetting('DB_DATABASE');
        $user = static::getSetting('DB_USER');
        $pass = static::getSetting('DB_PASS');
        $dsn = "mysql:dbname={$db};host={$host}";
        $pdo = new PDO($dsn, $user, $pass);
        $sql = 'SELECT * FROM replace_keyword';
        $pattern = [];
        $replacement = [];
        foreach ($pdo->query($sql) as $row) {
            $pattern[] = "/{$row['pattern']}/";
            $replacement[] = $row['keyword'];
        }
        return preg_replace($pattern, $replacement, $filename);
    }

	/**
	 * jpegファイルをフラットなファイル名にする
	 */
    public static function flatjpeg($path = ".")
	{
		if (substr($path, -1) != "/") {
			$path .= "/";
		}
		$res = static::findFiles($path);
		foreach ($res as $filename) {
			$info = pathinfo($filename);
			if (!isset($info['extension']) || strtolower($info['extension']) != "jpg") {
				continue;
			}
			$toname = str_replace("/", "_", $filename);
			$system="mv '$filename' '$path$toname'";
			static::exec("$system", ['log' => false]);
		}
	}

    public static function findFiles($path = ".") : array
	{
		$ret = [];
		$dh = opendir($path);
		if ($dh === false) {
			static::error("{$path}のオープンに失敗しました");
			return $ret;
		}
		while (($file = readdir($dh)) !== false) {
			if (substr($file, 0, 1) == ".") {
				continue;
			}
			$filename = "$path/$file";
			//print "$filename\n";
			if (is_dir($filename)) {
				$res = static::findFiles($filename);
				$ret =array_merge($ret, $res);
			}
			$ret[] = preg_replace("/^\.\//", "", $filename);
		}
		return $ret;
	}

    public static function isUnneededFile($file): bool
    {
        if ($file == "www.top-modelz.com" ||
            strpos($file, ".url") !== FALSE ||
            strpos($file, ".txt") !== FALSE ||
            strpos($file, "thumbs.db") !== FALSE ||
            strpos($file, "artofx.org") !== FALSE ||
            strpos($file, ".") === FALSE) // 拡張子無し
        {
            return true;
        }
        return false;
    }

    /**
     * 不要ファイル削除
     */
    public static function deleteUnneededFile($dir)
    {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== FALSE) {
                $file = strtolower($file);
                if (static::isUnneededFile($file)) {
                    $filename = "$dir/$file";
                    if (substr($dir, -1) == "/") {
                        $filename = "$dir$file";
                    }
                    echo "   削除 $filename\n";
					if (is_dir($filename) === false) {
						unlink("$filename");
					}
                }
            }
            closedir($dh);
        }
    }

    public static function snake($str): string
    {
        return ltrim(strtolower(preg_replace('/[A-Z]/', '_\0', $str)), '_');
    }

    public static function camel($str): string
    {
        return lcfirst(strtr(ucwords(strtr($str, ['_' => ' '])), [' ' => '']));
    }

    /**
     * 数字のファイル名に置き換える
     */
    public static function renameCode($path)
    {
        $file = "";
        $bExistsNumberFile = false;
        if ($dh = opendir($path)) {
            // すでに数字のファイルがあるかチェック
            while (($file = readdir($dh)) !== FALSE) {
                $file = explode(".", $file);
                if (preg_match("/^[0-9]+$/", $file[0])) {
                    $bExistsNumberFile = TRUE;
                    $file = implode(".", $file);
                    break;
                }
            }
            closedir($dh);
        }
        if (!$bExistsNumberFile) {
            $no = 1;
            if ($dh = opendir($path)) {
                $files = [];
                $cover = [];
                while (($file = readdir($dh)) !== FALSE) {
                    if (substr($file, 0, 1) == ".") {
                        continue;
                    }
                    // カバーを一旦除外しておく
                    if (stripos($file, "cover") !== false) {
                        $cover[] = $file;
                        continue;
                    }
                    $files[] = $file;

                }
                closedir($dh);
                natsort($files);
				$files = static::sortPages($files);
				static::error(__METHOD__."():".__LINE__.":".json_encode($files));
                // カバーを先頭に持ってくる
                foreach ($cover as $file) {
                    array_unshift($files, $file);
                }
                foreach ($files as $file) {
                    if (static::isUnneededFile($file)) {
                        continue;
                    }
                    $paths = explode(".", $file);
                    $ext = @array_pop($paths);
                    $system = sprintf("mv \"$path/$file\" \"$path/%03d.$ext\"", $no++);
                    static::exec("$system");
                }
            }
        } else {
            static::info("  すでに数字のファイルがある[$file]");
        }
    }

	public static function sortPages(array $files): array
	{
		usort($files, function($a, $b) {
			if (preg_match_all("/([0-9]+)/", $a, $match)) {
				$a = (int)array_pop($match[1]);
			} else {
				$a = 0;
			}
			if (preg_match_all("/([0-9]+)/", $b, $match)) {
				$b = (int)array_pop($match[1]);
			} else {
				$b = 0;
			}
			return $a - $b;
		});
		return $files;
	}

	public static function isPicture(string $ext) : bool
	{
		switch(strtolower($ext))
		{
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'gif':
			$ret = true;
			break;
		default:
			$ret = false;
			break;
		}
		return $ret;
	}

    /**
     * 解凍
     */
    public static function uncompress($filename, $tmpdir = "tmp_dir")
    {
        $arcive_exe_path = static::getSetting("arcive_exe_path", "/usr/local/bin/"); // "/cygdrive/c/windows/");
        $unzip_cmd = static::getSetting("unzip_cmd", "unzip");
        /* RAR */
        if (strtolower(substr($filename, -4)) == ".rar") {
            /*
                 -e  書庫のファイルを解凍
                　書庫から１個以上のファイルをカレントディレクトリまたは指定された
                  ディレクトリに解凍します。ただし、書庫中のディレクトリ階層の記録
                  を無視し、すべてのファイルを指定したディレクトリに展開します。

                 -p<password>
                     パスワードを指定します。

                 -q  解凍時の進捗ダイアログを表示しません。
            */
            $uncompresscmd = "{$arcive_exe_path}unrar32 -e -q '$filename' $tmpdir/ ";
        }
        /* ZIP */
        if (strtolower(substr($filename, -4)) == ".zip") {
            /*
                 -i   解凍状況の表示ダイアログを出す (default)
                      禁止するには --i と指定してください。

                 -P$$$ 暗号化ファイルに対して、$$$ をパスワードとして使用する。
                    暗号化はファイル毎に異なる可能性がありますが、これで指定できるのは
                    全てに共通で１個だけです。

            */
            $uncompresscmd = "$arcive_exe_path$unzip_cmd -j '$filename' -d $tmpdir/";
        }
        /* LZH */
        if (strtolower(substr($filename, -4)) == ".lzh") {
            $uncompresscmd = "{$arcive_exe_path}unlha32 x '$filename' $tmpdir/";
        }
        /* 7-ZIP */
        if (strtolower(substr($filename, -3)) == ".7z") {
            /**/
            $uncompresscmd = "{$arcive_exe_path}7z x -o$tmpdir/ '$filename'";
        }

        /* 解凍 */
        if ($uncompresscmd) {
            static::line(" ->$uncompresscmd");
			static::exec("$uncompresscmd");
            $uncompress = TRUE;

            $dh = opendir($tmpdir);
            while (($file = readdir($dh)) !== false) {
                $ext = strtolower(substr($file, -4));
                if (in_array($ext, ['.wmv', '.mp4', '.mpg', '.avi'])) {
                    $system = "mv $tmpdir/$file .";
					static::exec($system);
                }
            }
            closedir($dh);
            static::line(" ->delete");
            static::deleteUnneededFile($tmpdir);
            static::line(" ->rename");
            static::renameCode($tmpdir);
        }
    }

    /**
     * 圧縮
     */
    public static function compress($filename, $tmpdir = "tmp_dir")
    {
        $real_dir = str_replace(array("\r", "\n"), "", shell_exec("ls $tmpdir"));
        if (is_dir("$tmpdir/$real_dir")) {
            $zip_filename = "$real_dir.zip";
        } else {
            if (!empty($real_dir) && strpos($real_dir, "?") !== FALSE) {
                static::error("  読み込めないディレクトリが作成されました($real_dir)");
                exit;
            }
            $real_dir = "";
            $zip_filename = substr($filename, 0, -4) . ".zip";
        }
        $zip_filename = static::converOutputZipFilename($zip_filename);
        while (file_exists($zip_filename)) {
            $zip_filename = str_replace(".zip", "_.zip", $zip_filename);
        }
        static::deleteUnneededFile("$tmpdir/$real_dir");
        $system = "zip -9 -j '$zip_filename' '$tmpdir/$real_dir'/*";
        static::line(" ->$system\n");
		static::exec("$system");
        static::deleteTempDirectory();
    }

    /**
     * テンポラリディレクトリ削除
     */
    public static function deleteTempDirectory($tmpdir = "tmp_dir")
    {
        $system = "rm -rf \"$tmpdir\"";
		static::exec("$system");
    }

    /**
     * ビデオファイル？
     */
    public static function isVideo($filename)
    {
        $list = array(
            "vid.",
            "-archwayvid",
            "-bgvid",
            "-btsvid",
            "-vid",
            "-wgpvid",
        );
        $ret = FALSE;
        foreach ($list as $word) {
            if (strpos($filename, $word) !== FALSE) {
                $ret = TRUE;
                break;
            }
        }
        return ($ret);
    }

    /**
     * 解凍ディレクトリビデオファイルチェック
     *
     * 画像の圧縮であれば１０枚以上あるはず
     * かつ動画ファイルがあればビデオの圧縮とみなす
     */
    public static function isTempVideoCheck($tmpdir = "tmp_dir")
    {
        $system = "ls {$tmpdir}";
        $res = explode("\n", shell_exec($system));
        if (count($res) > 10 || count($res) - 1 < 1) {
            // １０枚以上あるので動画ではない
            return (FALSE);
        }
        $list = array(
            ".mp4",
            ".wmv",
            ".avi",
        );
        $ret = FALSE;
        foreach ($list as $word) {
            foreach ($res as $filename) {
                if (strpos(strtolower($filename), $word) !== FALSE) {
                    $ret = TRUE;
                }
            }
        }
        return ($ret);
    }

    /**
     * 変換済みかチェック
     */
    public static function isNoConvert($filename)
    {
        $list = array(
            "(ipod)",
        );
        $ret = FALSE;
        foreach ($list as $word) {
            if (strpos($filename, $word) !== FALSE) {
                $ret = TRUE;
                break;
            }
            if (file_exists(substr($filename, 0, -4) . $word . substr($filename, -4))) {
                $ret = TRUE;
                break;
            }
        }
        return ($ret);
    }

    /**
     * テンポラリディレクトリ取得
     */
    public static function getTempDirectory()
    {
        return sys_get_temp_dir();
    }

    /**
     * デフォルト付き設定取得
     */
    public static function getSetting($name, $default = "")
    {
        $home = static::getHome();
        $config = parse_ini_file("$home/.rezip");
        if (isset($config[$name])) {
            $ret = $config[$name];
        } else {
            $ret = $default;
        }
        return $ret;
    }

    public static function moveTrash(string $filename)
    {
        if (file_exists($filename) === false) {
            static::error(" ->{$filename}が見つかりません");
            return;
        }
        static::info(" -> [$filename]をゴミ箱へ");
        $filename = realpath($filename);
        $cmd =
            "osascript -e \"\"\"\n".
            "tell application \\\"Finder\\\"\n".
            "move POSIX file \\\"$filename\\\" to trash\n".
            "end tell\n".
            "\"\"\"".
            "";
        static::exec($cmd, ['log' => false]);

    }

    public static function notice(string $message="")
    {
        $cmd = "osascript -e 'display notification \"hogehoge\" with title \"Fuga\"'";
        static::exec($cmd, ['log' => false]);

    }
    public static function getHome() :string
    {
        return getenv("HOME");
    }

    public static function exec(string $cmd, array $opt = [])
    {
		$enableLog = $opt['log'] ?? true;
        $pwd = getcwd();
		if ($enableLog) {
			static::line(" -> $cmd");
			//static::debug(__METHOD__."():".__LINE__.":pwd[{$pwd}]:cmd[{$cmd}]");
		}
        if (strpos($cmd,">") !== false) {
            static::info(" ->リダイレクト指定");
        } else {
            $cmd .= " 2>&1 > /dev/null";
        }
        //static::info($cmd);
        return system($cmd);
    }

    public static function __callStatic(string $name ,array $arguments)
    {
        $ouputList = [
            "line",
            "info",
            "warn",
            "error",
            "alert",
            "debug",
        ];
        if (in_array($name, $ouputList) === false) {
            throw new \Exception("未定義のメソッド($name)です");
        }
        //$cmd = static::getOutPutObject();
        print implode("\n",$arguments)."\n";
    }
}
