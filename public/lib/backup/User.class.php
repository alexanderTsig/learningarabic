<?php

require_once '/var/www/portal.pathtoarabic.com/public/amember4/library/Am/Lite.php';
require_once 'Application.class.php';
require_once 'PdoFactory.class.php';
require_once 'Validate.class.php';
require_once 'Teacher.class.php';
require_once 'AmemberRest.class.php';
require_once 'Number.class.php';

class User {
	private static $instance;
	protected $id;

	public $fullname;
	public $email;

	# The object constructor. aMember handles the user session. If the user is
	# signed in then his or her id will be determined with the support of AM_Lite.
	#
	public function __construct($id = null) {
		if ($id === null) {
			$am_user = AM_Lite::getInstance()->getUser();

			$this->id = $am_user['user_id'];

			# $this->fullname = AM_Lite::getInstance()->getName();
			# $this->email = AM_lite::getInstance()->getEmail();
		}
		else {
			$this->id = $id; // This is useful for debugging
		}
	}

	# Embed an instance of the object as a Singleton. The principal advantage of
	# using the Factory pattern to obtain an instance is that we will avoid the
	# overhead incurred by the constructor within the scope of a request.
	# It also means that we can return a Teacher object where appropriate.
	#
	public static function getInstance() {
		if (self::$instance === null) {
			$am_user = AM_Lite::getInstance()->getUser();

			if ($am_user !== null && array_key_exists('access_level', $am_user) && $am_user['access_level'] == 1) {
				self::$instance = new Teacher();
			}
			else {
				self::$instance = new User();
			}
		}

		return self::$instance;
	}

	# Calculate the current level of the user
	#
	private function calculateLevel() {
		$levels = Application::getLevelIdentifiers();

		foreach ($levels as $level) {
			$lesson_count = count(Application::getLessons($level));
			$tests_passed = $this->calculateTestsPassed($level);

			if ($tests_passed < $lesson_count)
				return $level;
		}

		return end($levels);
	}

	# Calculate the number of tests passed in a given level. If the given level is
	# null then all levels are taken into consideration.
	#
	public function calculateTestsPassed($level = null, $min_lesson = 1) {
		if ($level !== null && ! Validate::level($level, __METHOD__))
			return null;

		$where_clause = ($level === null)
			? 'user_id = :user_id AND score >= passing_score'
			: 'user_id = :user_id AND level_id = :level AND score >= passing_score';

		$sql = <<<"SQL"
			SELECT COUNT(*)
			FROM test_result
			NATURAL JOIN (
				SELECT lesson_id, MAX(score) AS score
				FROM test_result
				WHERE $where_clause AND lesson_id >= :min_lesson
				GROUP BY lesson_id
			) AS t
			WHERE user_id = :user_id
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
$stmt->bindValue(':min_lesson', $min_lesson, PDO::PARAM_INT);

		if ($level !== null)
			$stmt->bindValue(':level', $level, PDO::PARAM_INT);

		$stmt->execute();

		return $stmt->fetchColumn(0);
	}

	# Calculate the overall progress of the user as a percentage. This is done by
	# dividing the number of lessons by the number of tests passed, either for a
	# given level or the entire course.
	#
	private function calculateProgress($level = null) {
		if ($level !== null && ! Validate::level($level, __METHOD__))
			return null;

		return round($this->getTestsPassed($level) / count(Application::getLessons($level)) * 100);
	}

# PUBLIC METHODS

	public static function checkLogin($user, $password) {
		return AmemberRest::checkAccessByLoginPass($user, $password);	
	}

	# If the user's membership is chronologically scoped then return the expiry date as a DateTime object.
	# 
	public function getExpiryDate() {
		$date_string = Am_Lite::getInstance()->getExpire(Am_Lite::ANY);

		if ($date_string === null)
			return null;

		# FIXME: check whether aMember returns dates differently depending on a user's timezone
		$date = DateTime::createFromFormat('Y-m-d H:i:s', "$date_string 00:00:00", new DateTimeZone('UTC'));
		
		if ($date === false) {
			Log::error("Unable to convert expiry date to DateTime object: $date_string");
			return null;
		}
		else {
			return $date;
		}
	}

	# Test whether a membership expires within a given period of time, in seconds.
	#
	public function membershipExpiresIn($seconds) {
		$expiry_date = $this->getExpiryDate();
		return ($expiry_date !== null && $expiry_date->format('U') - time() <= $seconds);
	}

	# Determine whether the user is currently signed in
	#	
	public function isLoggedIn() {
		return Am_Lite::getInstance()->isLoggedIn();
	}

	# Determine whether the user has a valid (non-expired) membership
	#
	public function isMembershipValid() {
		return Am_Lite::getInstance()->haveSubscriptions(Am_Lite::ANY);
	}

	public function getUserId() {
		# The object constructor. aMember handles the user session. If the user is
		# signed in then his or her id will be determined with the support of AM_Lite.
		#if ($this->id === null) {
		#	$am_user = AM_Lite::getInstance()->getUser();
		#	$this->id = $am_user['user_id'];
		#}

		return $this->id;
	}

	# Return the user's name
	#
	public function getName() {
		return AM_Lite::getInstance()->getName();
	}

	# Return the username
	#
	public function getUsername() {
		return AM_Lite::getInstance()->getUsername();
	}

	# Return the user's email address
	#
	public function getEmail() {
		$email = AM_Lite::getInstance()->getEmail();
		return strtolower(trim( $email ));
	}

	# FIXME: Use memcache
	#
	public function getLevel() {
		$calculated_level = $this->calculateLevel();
		$start = $this->getStartPosition();

		if ($calculated_level < $start['level']) {
			return $start['level'];
		}
		else {
			return $calculated_level;
		}
	}

	# FIXME: Use memcache
	#
	public function getTestsPassed($level = null) {
		$levels = ($level === null) ? Application::getLevelIdentifiers() : array($level);
		$start = $this->getStartPosition();
		$tests_passed = 0;

		foreach ($levels as $level) {
			$min_lesson = 1;

			# If the level is lower than the user's start level then count all possible tests as having been passed
			if ($level < $start['level']) {
				$tests_passed += count(Application::getLessons($level));
				continue;
			}
			# If the level is equal to the user's start level then count all possible tests up to the start lesson as having been passed (if applicable)
			elseif ($level === $start['level'] && $start['lesson'] > 1) {
				$min_lesson = $start['lesson'];
				$tests_passed += $min_lesson - 1;
			}

			$tests_passed += $this->calculateTestsPassed($level, $min_lesson);
		}

		return $tests_passed;
	}

	# FIXME: Use memcache
	#
	public function getProgress($level = null) {
		return $this->calculateProgress($level);
	}

	# Determine the user's preferred teacher gender
	#
	public function getPreferredGender() {
		$am_user = AM_Lite::getInstance()->getUser();

		switch ($am_user['preferred_gender']) {
			case 1:
				return "M";

			case 2:
				return "F";

			default:
				Log::warning("User #" . $this->id . " has no preferred gender! Defaulting to M");
				return "M";
		}
	}

	# FIXME: Use memcache
	#
	public function getTeachers($level = null) {
		return $this->enumerateTeachers($level, $this->getPreferredGender());
	}

	# For a given level/lesson tuple, return the tuple for the preceding lesson,
	# provided that the user is allowed to access it (otherwise it returns null).
	# This is particularly useful for navigation buttons.
	#
	public function getPreviousLesson($level, $lesson) {
		# The Slim framework always passes parameters as strings
		$level = Number::toInteger($level);
		$lesson = Number::toInteger($lesson);

		if ($level > 1 && $lesson === 1) {
			$level--;
			$lesson = count(Application::getLessons($level));
		}
		else if ($lesson > 1) {
			$lesson--;
		}
		else {
			return null;
		}

		if ($this->getLevel() < $level || ($this->getLevel() === $level && $lesson > $this->getTestsPassed($level)))
			return null;

		return array($level, $lesson);
	}

	# For a given level/lesson tuple, return the tuple for the following lesson,
	# provided that the user is allowed to access it (otherwise it returns null).
	# This is particularly useful for navigation buttons.
	#
	public function getNextLesson($level, $lesson) {
		# The Slim framework always passes parameters as strings
		$level = Number::toInteger($level);
		$lesson = Number::toInteger($lesson);

		if ($lesson < count(Application::getLessons($level))) {
			$lesson++;

			if ($this->getLevel() < $level || ($this->getLevel() === $level && $lesson > $this->getTestsPassed($level)))
				return null;

		}
		else if ($level < count(Application::getLevels())) {
			$level++;
			$lesson = 1;

			if ($this->getLevel() < $level)
				return null;
		}
		else {
			return null;
		}

		return array($level, $lesson);
	}

	# For a given level, return the highest lesson available to the user
	#
	public function getMaxLesson($level = null) {
		if ($level === null)
			$level = $this->getLevel();

		$max_lesson = $this->getTestsPassed($level);
		$start = $this->getStartPosition();

		if ($level === $start['level'] && $max_lesson < $start['lesson'])
			$max_lesson = $start['lesson'];

		return ($max_lesson === count(Application::getLessons($level)))
			? $max_lesson
			: $max_lesson + 1;
	}

	# Return a list of lessons for a given level. If the level is not defined then
	# it defaults to the user's current level. This method is different to that in
	# the Application class because it includes the user's test results.
	#
	public function getLessons($level = null) {
		if ($level === null)
			$level = $this->getLevel();

		$lessons = Application::getLessons($level);

		if ($lessons === false)
			return false;

		$sql = <<<"SQL"
			SELECT lesson_id, score, passing_score, timestamp
			FROM test_result
			NATURAL JOIN (
				SELECT lesson_id, MAX(score) AS score
				FROM test_result
				WHERE user_id = :user_id AND level_id = :level
				GROUP BY lesson_id
			) AS t
			WHERE user_id = :user_id
			ORDER BY lesson_id ASC
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
		$stmt->bindValue(':level', $level);
		$stmt->execute();

		#$test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
		#
		#for ($i = 0; $i < count($test_results); $i++) {
		#	$lessons[$i] = array_merge($lessons[$i], $test_results[$i]);
		#}

		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$i = $row['lesson_id'];
			$lessons[$i]['score'] = $row['score'];
			$lessons[$i]['passing_score'] = $row['passing_score'];
			$lessons[$i]['timestamp'] = $row['timestamp'];
		}

		foreach ($lessons as &$lesson) {
			if (array_key_exists('score', $lesson)) {
				$lesson['test_status'] = ($lesson['score'] >= $lesson['passing_score']) ? "passed" : "failed";
			}
			else {
				$lesson['test_status'] = "skipped";
			}
		}

		return $lessons;
	}

# TODO: Methods under construction

	public function getStartPosition() {
		$am_user = AM_Lite::getInstance()->getUser();

		$startlevel = $am_user['startlevel'];

		if ($startlevel === null) {
			Log::debug("User " . $this->id . " has an undefined startlevel. Defaulting to 1.1");

			return array(
				'level'  => 1,
				'lesson' => 1
			);
		}
		else {
			if (preg_match('/^(\d+)\.(\d+)$/', $startlevel, $match) === 1) {
				return array(
					'level'  => intval($match[1]),
					'lesson' => intval($match[2])
				);
			}
			else {
				Log::error("Invalid startlevel from aMember: $startlevel");
			}
		}
	}

	public function setTestResults($args) {
		$defaults = array(
			"max_score"     => 100,
			"min_score"     => 0,
			"passing_score" => 70
		);

		$bad_arg = false;

		foreach (array('passing_score', 'min_score', 'max_score', 'points', 'max_points') as $key) {
			if (! (array_key_exists($key, $args) && Number::isInteger($args[$key])) ) {
				Log::error(__METHOD__ . " given invalid argument; $key is either undefined or not a valid integer");
				$bad_arg = true;
			}
		}

		if ($bad_arg === true)
			return null;

		$args = array_merge($defaults, $args);

		$sql = <<<"SQL"
			INSERT INTO test_result (user_id, level_id, lesson_id, score, passing_score, min_score, max_score, points, max_points)
			VALUES (:user_id, :level_id, :lesson_id, :score, :passing_score, :min_score, :max_score, :points, :max_points);
SQL;

		$stmt = PdoFactory::getInstance()->prepare($sql);

		Log::debug("User " . $this->getUserId() . ' passed exam ' . $args['level'] . '/' . $args['lesson'] . ' with a score of ' . $args['score']);

		$rc = $stmt->execute(array(
			':user_id'       => $this->getUserId(),
			':level_id'      => $args['level'],
			':lesson_id'     => $args['lesson'],
			':score'         => $args['score'],
			':passing_score' => $args['passing_score'],
			':min_score'     => $args['min_score'],
			':max_score'     => $args['max_score'],
			':points'        => $args['points'],
			':max_points'    => $args['max_points']
		));

		if ($rc === false)
			return null;

		return ($args['score'] >= $args['passing_score']);
	}

	# Retrieve the user's notes for a given level/lesson tuple
	#
	public function getNote($level, $lesson) {
		$sql = 'SELECT text FROM note WHERE user_id = :user_id AND level = :level AND lesson = :lesson';
		$stmt = PdoFactory::getInstance()->prepare($sql);
		$stmt->bindValue(':user_id', $this->getUserId(), PDO::PARAM_INT);
		$stmt->bindValue(':level', $level, PDO::PARAM_INT);
		$stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
		$stmt->execute();

		if ($stmt->rowCount() === 0)
			return null;

		return $stmt->fetch(PDO::FETCH_COLUMN, 0);

	}

	# Store user notes for a given level/lesson tuple
	#
	public function setNote($level, $lesson, $text) {
		$sql = <<<"SQL"
			INSERT INTO note (user_id, level, lesson, text) VALUES (:user_id, :level, :lesson, :text)
			ON DUPLICATE KEY UPDATE text = :text
SQL;
		$stmt = PdoFactory::getInstance()->prepare($sql);

		return $stmt->execute(array(
			':user_id'   => $this->getUserId(),
			':level'     => $level,
			':lesson'    => $lesson,
			':text'      => $text
		));
	}

	# Store a user preference as a key/value pair in volatile storage
	#
	public function setPref($pref, $value) {
		if ($value === "true") {
			$value = true;
		}
		elseif ($value === "false") {
			$value = false;
		}

		$cache = Application::getMemcachedInstance();
		$key_name = 'uid_' . $this->getUserId() . '_pref_' . $pref;
		$cache->delete($key_name);
		$cache->set($key_name, $value);
	}

	# Retrive a user preference by its key from volatile storage
	#
	public function getPref($pref) {
		$key_name = 'uid_' . $this->getUserId() . '_pref_' . $pref;
		return Application::getMemcachedInstance()->get($key_name);
	}

	# Request a nonce. This is currently used to prevent the forgery of submitted exam results.
	#
	public function createNonce($ip, $class = 'undefined') {
		$uuid = Application::createUUID();

		$sql = <<<"SQL"
			INSERT INTO nonce (user_id, ip, class, value, created_on)
			VALUES (:user_id, :ip, :class, :value, NOW())
			ON DUPLICATE KEY UPDATE value = :value, created_on = NOW()
SQL;
		$stmt = PdoFactory::getInstance()->prepare($sql);

		$rc = $stmt->execute(array(
			':user_id' => $this->id,
			':ip'      => $ip,
			':class'   => $class,
			':value'   => $uuid
		));

		return ($rc === true) ? $uuid : null;
	}

	# Verify a nonce. This is currently used to prevent the forgery of submitted exam results.
	# Note that nonces more than one hour old will not be considered valid.
	#
	public function verifyNonce($value, $ip, $class = 'undefined') {
		if ($value !== null && preg_match('/^[[:alnum:]]{40}$/', $value) === 0)
			return false;

		$sql = <<<"SQL"
			SELECT COUNT(*) FROM nonce
			WHERE user_id = :user_id AND ip = :ip AND class = :class AND value = :value AND created_on >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
SQL;
		$stmt = PdoFactory::getInstance()->prepare($sql);
		
		$stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
		$stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
		$stmt->bindValue(':class', $class, PDO::PARAM_STR);
		$stmt->bindValue(':value', $value, PDO::PARAM_STR);
		
		$stmt->execute();

		return $stmt->fetchColumn(0) == 1 ? true : false;
	}
}
