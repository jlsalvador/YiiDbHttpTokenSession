<?php

/**
 * @author José Luis Salvador Rufo <salvador.joseluis@gmail.com>
 * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
 * @version: 201308081252
 */
class DbHttpTokenSession extends CDbHttpSession
{

	/**
	 * @var string the $_REQUEST index name that will store a token id.
	 * Defaults to '_t'.
	 */
	public $tokenRequestKeyName = '_t';

	/**
	 * @var string the $_SERVER index name that will store a token id.
	 * Defaults to 'HTTP_TOKEN'.
	 */
	public $tokenHeaderKeyName = 'HTTP_TOKEN';

	/**
	 * @var integer the number of seconds after which data will be seen as
	 * 'garbage' and cleaned up, defaults to 1440 seconds.
	 */
	public $tokenTimeout = 1440;

	/**
	 * @var string the name of the DB table to store token content.
	 * Note, if {@link autoCreateTokenTable} is false and you want to create
	 * the DB table manually by yourself, you need to make sure the DB table
	 * is of the following structure:
	 * <pre>
	 * (id CHAR(32) PRIMARY KEY, expire INTEGER, session_id CHAR(32))
	 * </pre>
	 * @see autoCreateTokenTable
	 */
	public $tokenTableName = 'YiiToken';

	/**
	 * @var boolean whether the token DB table should be automatically created
	 * if not exists. Defaults to true.
	 * @see tokenTableName
	 */
	public $autoCreateTokenTable = true;

	/**
	 * @var string the actual token id.
	 */
	public $tokenId;

	/**
	 * @var string the last token id.
	 */
	public $oldTokenId;

	/**
	 * @var bool flag that determine if the token is new.
	 */
	public $isNewToken = false;

	/**
	 * (non-PHPdoc)
	 * @see CHttpSession::init()
	 */
	public function init()
	{
		$this->setCookieMode('none'); // disable session cookies.
		$this->setUseTransparentSessionID(false); // disable PHPSESSID.
		parent::init();
	}

	/**
	 * Generate a new token id.
	 * @return string new unique token id.
	 */
	protected function generateTokenId()
	{
		do {
			$newId = md5(microtime(true) . uniqid(true) . __CLASS__);
			$isNewId = !$this->getDbConnection()
				->createCommand()
				->select('id')
				->from($this->tokenTableName)
				->where('id=:newId', array(':newId' => $newId))
				->queryRow();
		} while (!$isNewId);
		return $newId;
	}

	/**
	 * Creates the token DB table.
	 * @param CDbConnection $db the database connection
	 * @param string $tableName the name of the table to be created
	 */
	protected function createTokenTable($db, $tableName)
	{
		$db->createCommand()->createTable($tableName, array(
			'id' => 'CHAR(32) PRIMARY KEY',
			'expire' => 'integer',
			'session_id' => 'CHAR(32)',
		));
	}

	/**
	 * Token open handler.
	 * Do not call this method directly.
	 * @throws CHttpException
	 * @return boolean whether token is opened successfully
	 */
	protected function openToken()
	{
		//Find the actual token id from the request.
		if (isset($_GET[$this->tokenRequestKeyName])
			 && preg_match('/^[a-fA-F\d]{32}$/', $_GET[$this->tokenRequestKeyName])
		) {
			$oldTokenId = $_GET[$this->tokenRequestKeyName];
		} elseif (isset($_SERVER[$this->tokenHeaderKeyName])
				&& preg_match('/^[a-fA-F\d]{32}$/', $_SERVER[$this->tokenHeaderKeyName])
		) {
			$oldTokenId = $_SERVER[$this->tokenHeaderKeyName];
		} else {
			$oldTokenId = null;
		}

		//Get the actual session id or create a new session with his new token.
		if ($oldTokenId) {
			$tokenRS = $this->getDbConnection()->createCommand()
				->select('id, session_id')
				->from($this->tokenTableName)
				->where('id=:tokenId AND expire>:expire', array(
					':tokenId' => $oldTokenId,
					':expire' => time()
				))
				->limit(1)->queryRow();
		} else {
			$tokenRS = false;
		}
		if ($tokenRS) { // token data found from the request.
			$this->isNewToken = false; // set token already exists in database.
			$this->oldTokenId = $tokenRS['id']; // set the old token id.
			session_id($tokenRS['session_id']); // set the session id from token before session_start().
		} else {
			$this->isNewToken = true; // set flag to insert a new token into database.
		}

		$newTokenId = $this->generateTokenId(); // always regenerate the token id for each request.
		$this->tokenId = $newTokenId;
		header('Token: ' . $newTokenId); // send the token id to http header.

		return true;
	}

	/**
	 * Session open handler.
	 * Do not call this method directly.
	 * @param string $savePath session save path
	 * @param string $sessionName session name
	 * @return boolean whether session is opened successfully
	 */
	public function openSession($savePath, $sessionName)
	{
		if ($this->autoCreateTokenTable) {
			$db = $this->getDbConnection();
			$db->setActive(true);
			try {
				$db->createCommand()
					->delete($this->tokenTableName, 'expire<:expire', array(
						':expire' => time()
					));
			} catch (Exception $e) {
				$this->createTokenTable($db, $this->tokenTableName);
			}
		}
		return parent::openSession($savePath, $sessionName) && $this->openToken();
	}

	/**
	 * Session and Token GC (garbage collection) handler.
	 * Do not call this method directly.
	 * @param integer $maxLifetime the number of seconds after which data will
	 * be seen as 'garbage' and cleaned up.
	 * @return boolean whether session and token is GCed successfully
	 */
	public function gcSession($maxLifetime)
	{
		$this->getDbConnection()->createCommand()
			->delete($this->tokenTableName, 'expire<:expire', array(
				':expire' => time()
			));
		return parent::gcSession($maxLifetime);
	}

	/**
	 * Save the token data and destruct the DbHttpTokenSession application component.
	 * @throws CHttpException 500, can't save the token.
	 */
	public function __destruct()
	{
		//Get the session id or create new one.
		$sessionId = session_id();
		if (!$sessionId) {
			session_start();
			$sessionId = session_id();
		}

		//Save token into database.
		$db = $this->getDbConnection();
		if ($this->isNewToken) { // the token is new, insert a new one.
			$allOk = $db->createCommand()
				->insert($this->tokenTableName, array(
					'id' => $this->tokenId,
					'expire' => time() + $this->tokenTimeout,
					'session_id' => $sessionId
				));
		} else { // the token already exists, update him.
			$allOk = $db->createCommand()
				->update($this->tokenTableName, array(
					'id' => $this->tokenId,
					'expire' => time() + $this->tokenTimeout,
					'session_id' => $sessionId
				), 'id=:oldId', array(
					':oldId' => $this->oldTokenId
				));
		}
		if (!$allOk) {
			throw new CHttpException(500, 'Can\'t save the token.');
		}
	}

}
