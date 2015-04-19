<?php

require_once '/var/www/portal.pathtoarabic.com/public/amember4/library/Am/Lite.php';
require_once 'PdoFactory.class.php';
require_once 'Validate.class.php';
require_once 'Log.class.php';

class Application {

	protected static $exam_path = '/var/www/portal.pathtoarabic.com/public/assets/exam';
	protected static $exercise_path = '//ptaexercise.s3.amazonaws.com/';

	const MAX_CLIPS = 2; // Allow up to two video clips per lesson/level tuple

	public static function getLevelIdentifiers() {
		$stmt = PdoFactory::getInstance()->prepare('SELECT id  FROM level ORDER by id ASC');
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	public static function getLevels() {
		$stmt = PdoFactory::getInstance()->prepare('SELECT id, title, description FROM level ORDER by id ASC');
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/* public static function getLessons($level = null) {
		if ($level !== null && ! Validate::level($level, __METHOD__))
			return null;

		if ($level === null) {
			$stmt = PdoFactory::getInstance()->prepare('SELECT level, lesson, title_en, title_ar FROM lesson ORDER BY level ASC, lesson ASC');
		}
		else {
			$stmt = PdoFactory::getInstance()->prepare('SELECT level, lesson, title_en, title_ar FROM lesson WHERE level = :level ORDER BY level ASC, lesson ASC');
			$stmt->bindValue(':level', $level, PDO::PARAM_INT);
		}

		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	} */

	public static function getLessons($level = null) {
		if ($level !== null && ! Validate::level($level, __METHOD__))
			return null;

		if ($level === null) {
			$stmt = PdoFactory::getInstance()->prepare('SELECT level, lesson, title_en, title_ar FROM lesson ORDER BY level ASC, lesson ASC');
		}
		else {
			$stmt = PdoFactory::getInstance()->prepare('SELECT level, lesson, title_en, title_ar FROM lesson WHERE level = :level ORDER BY level ASC, lesson ASC');
			$stmt->bindValue(':level', $level, PDO::PARAM_INT);
		}

		$stmt->execute();

		$lessons = array();

		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$lesson = array(
				'level'    => $row['level'],
				'lesson'   => $row['lesson'],
				'title_en' => $row['title_en'],
				'title_ar' => $row['title_ar']
			);

			# FIXME: Consider using consistent data structure here ...
			if ($level === null) {
				$lessons[] = $lesson;
			}
			else {
				# If a level was specified then have the array indices equate with the lesson number.
				# This is convenient for User::getLessons() because it needs to merge additional data with the array.
				$lessons[$row['lesson']] = $lesson;
			}
		}

		return $lessons;
	}

	public static function getTeachers($level = null) {
		if ($level !== null && ! Validate::level($level, __METHOD__))
			return null;

		$sql = null;

		if ($level === null) {
			$sql = 'SELECT id, name FROM teacher ORDER BY teacher.name';
		}
		else {
			$sql = <<<'SQL'
				SELECT teacher.id, teacher.name, teacher.gender
				FROM teacher, teacher_level
				WHERE teacher.id = teacher_level.teacher_id AND teacher_level.level = :level
				ORDER by teacher.name
SQL;
		}

		$stmt = PdoFactory::getInstance()->prepare($sql);

		if ($level !== null)
			$stmt->bindValue(':level', $level, PDO::PARAM_INT);

		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function getNetConnectionURL() {
		return 'rtmp://s1ztnteh16mttb.cloudfront.net/cfx/st';
	}

	public static function getVideoCookie($level, $lesson, $gender) {
		if (! Validate::lesson($level, $lesson, __METHOD__))
			return null;

		$sql = <<<'SQL'
			SELECT video.cookie
			FROM video
			LEFT JOIN teacher
			ON video.teacher_id = teacher.id
			WHERE level = :level AND lesson = :lesson AND (teacher.gender = :gender OR video.teacher_id IS NULL)
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);
		
		$stmt->bindValue(':level', $level, PDO::PARAM_INT);
		$stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
		$stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
		
		$stmt->execute();

		if ($stmt->rowCount() === 0)
			return null;

		$count = 1;
		$cookies = array();

		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $cookie) {
			if ($count > self::MAX_CLIPS) {
				Log::warning("Too many clips in video table where level = $level and lesson = $lesson. Limiting to " . self::MAX_CLIPS . " result(s).");
				return $cookies;
			}

			$cookies[] = "$cookie";
			$count++;
		}

		return $cookies;
	}

	public static function getVideoChapters($level, $lesson, $gender) {
		if (! Validate::lesson($level, $lesson, __METHOD__))
			return null;

		$sql = <<<'SQL'
			SELECT video.cookie, video_chapter.title, video_chapter.start
			FROM video_chapter, video
			LEFT JOIN teacher ON video.teacher_id = teacher.id
			WHERE video.level = :level AND video.lesson = :lesson
				AND video_chapter.video_id = video.id
				AND (teacher.gender = :gender OR video.teacher_id IS NULL)
			ORDER BY video.clip ASC, video_chapter.start ASC
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);
		
		$stmt->bindValue(':level', $level, PDO::PARAM_INT);
		$stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
		$stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
		
		$stmt->execute();

		if ($stmt->rowCount() === 0)
			return null;

		$chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		# If there is no chapter defined for the very beginning of the clip then insert one here
		if ($chapters[0]['start'] !== '00:00:00:00' && $chapters[0]['start'] !== '00:00:00.00') {
			array_unshift($chapters, array(
				'cookie' => $chapters[0]['cookie'],
				'title'  => 'Introduction',
				'start'  => '00:00:00.00'
			));
		}

		return $chapters;
	}

	# Converts the output of getVideoChapters() to a format suitable for initializing flowplayer.
	# Upstream is expected to convert to JSON as necessary.
	#
	public static function getVideoPlaylist($level, $lesson, $gender) {
		$chapters = self::getVideoChapters($level, $lesson, $gender);
	
		if ($chapters === null)
			return null;

		$clips = array();
		$last_cookie = null;
		$cuepoints = array();

		for ($i = 0; $i < count($chapters); $i++) {
			$chapter = $chapters[$i];
			$cookie = $chapter['cookie'];
			$cursor = $chapter['start'];

			if ($i === 0 || $cookie === $last_cookie) {
				# Collect the cuepoint
				$cuepoints[] = self::cursorToSeconds($cursor) * 1000;
			}
			else {
				# We're iterating over a new clip. Stash the preceding clip along with its cuepoints.
				$clips[] = array(
					'url'       => "mp4:$last_cookie",
					'cuepoints' => $cuepoints,
					'autoPlay'  => (count($clips) === 0) ? false : true # don't autoplay the first clip
				);

				# Don't forget to set the first cuepoint for the new clip.
				$cuepoints = array(self::cursorToSeconds($cursor) * 1000);
			}
		
			if ($i === (count($chapters) - 1)) {
				# This is the final cuepoint. Stash the current clip along with its cuepoints before returning.
				$clips[] = array(
					'url'       => "mp4:$cookie",
					'cuepoints' => $cuepoints,
					'autoPlay'  => (count($clips) === 0) ? false : true # don't autoplay the first clip
				);
		
				# Prepend a splash image to the playlist which contains the logo and lesson number in English and Arabic
				array_unshift($clips, array(
					'url'      => "//ptaimg.s3.amazonaws.com/lesson_$lesson.jpg",
					'scaling'  => 'orig',
					'autoPlay' => true
				));

				return $clips;
			}

			$last_cookie = $cookie;
		}
	}

	public static function cursorToSeconds($cursor) {
		// FIXME: some values have semicolon as last separator instead of a colon
		$part = preg_split("/[:\.]/", $cursor, 4);

		$hours = $part[0];
		$minutes = $part[1];
		$seconds = $part[2];
		$frames = $part[3];

		return round($hours * 3600 + $minutes * 60 + $seconds + ($frames * 0.04));
	}

	public static function getExerciseURL($level, $lesson) {
		if (! Validate::lesson($level, $lesson, __METHOD__))
			return null;

		$swf = self::$exercise_path . "${level}/${lesson}.swf";

		# FIXME: should check if response is XML containing error from Amazon

		return $swf;

		#else {
		#	Log::error("Unable to open exam asset: $swf");
		#}
	}

	public static function getExamURL($level, $lesson) {
		if (! Validate::lesson($level, $lesson, __METHOD__))
			return null;
	
		$swf = self::$exam_path . "/${level}/${lesson}/quiz.swf";

		if (is_readable($swf)) {
			return "//portal.pathtoarabic.com/assets/exam/${level}/${lesson}/quiz.swf";
		}
		else {
			Log::error("Unable to open exam asset: $swf");
		}
	}

	public static function getLoginURL() {
		return Am_Lite::getInstance()->getLoginURL();
	}

	public static function renderLoginForm() {
		return Am_Lite::getInstance()->renderLoginForm();
	}

	public static function getAnnouncements($max_results = 10, $date_format = 'l, M Y') {
		if (! Number::isInteger($max_results))
			$max_results = 10;
		
		$sql = 'SELECT id,title,text,UNIX_TIMESTAMP(created_on) as epoch FROM announcements ORDER BY created_on DESC LIMIT :max_results';
		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':max_results', $max_results, PDO::PARAM_INT);
		$stmt->execute();

		if ($stmt->rowCount() === 0)
			return null;

		$results = array();
		$i = 0;
		
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$results[$i] = $row;
			$results[$i]['date'] = date($date_format, $row['epoch']); # insert friendly date
			$i++;
		}

		return $results;
	}

	public static function getAnnouncement($id) {
		if (! Number::isInteger($id))
			return false;

		$sql = 'SELECT id,title,text,UNIX_TIMESTAMP(created_on) as epoch FROM announcements WHERE id = :id';
		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->execute();

		if ($stmt->rowCount() === 0)
			return null;

		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	
	public static function getMemcachedInstance() {
		$cache = new Memcached();
		$cache->addServer('localhost', 11211);
		return $cache;
	}

	public static function createUUID() {
		return chop(shell_exec('/usr/bin/openssl rand 20 -hex'));
	}
}
