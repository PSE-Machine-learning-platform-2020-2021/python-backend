<?
use PHPUnit\Framework\TestCase;
include("databaseConnection.php");

final class DataBaseConnectionTest extends TestCase {
	private static $dbh;
	
	public function setUpBeforeClass(): void {
		self::$db = new DataBaseConnection();
		
	}
	
	public function tearDownAfterClass(): void {
		self::$db = null;
	}
	
	public function test_get_language_metas(): void {
		$this->setOutputCallBack(function($output) {
			$data = json_decode($output, true);
			$this->assertIsArray($data);
			$this->assertGreaterThanOrEqual(1, count($data));
			foreach($data as $row) {
				$this->assertArrayHasKey("languageCode", $data);
				$this->assertIsString($data["languageCode"]);
				$this->assertArrayHasKey("languageName", $data);
				$this->assertIsString($data["languageName"], $data);
			}
		});
		self:$db->get_language_metas();		
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
	
	public function test_(): void {
		$this->markTestIncomplete();
	}
}
?>