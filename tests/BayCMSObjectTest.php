<?php

class BayCMSObjectTest extends \PHPUnit\Framework\TestCase {

    protected \BayCMS\Base\BayCMSContext $context;
    protected \BayCMS\Base\BayCMSContext $context_guest;
    protected \BayCMS\Base\BayCMSContext $context_www;
    protected function setUp(): void {
        $this->context = new \BayCMS\Base\BayCMSContext('/local/www/btineu',['id'=>5001,'id_benutzer'=>5002]);
        $this->context_guest = new \BayCMS\Base\BayCMSContext('/local/www/btineu',['id'=>5001,'id_benutzer'=>27724]);

        $_SERVER['HTTP_HOST']='localhost';
        $_SERVER['PHP_SELF']='/btineu/index.php';
        $this->context_www = new \BayCMS\Base\BayCMSContext('/local/www/btineu',['id_benutzer'=>27724]);

    }

    public function testLoadWww(){
        $obj = new \BayCMS\Base\BayCMSObject($this->context_www);
        $obj->load(5002);
        try {
            $obj->checkAccess();
        } catch (\Exception $e) {
            $this->assertEquals('You have no write access to object 5002', $e->getMessage());
        }
    }    
    
    public function testLoad(){
        $obj = new \BayCMS\Base\BayCMSObject($this->context_guest);
        $obj->load(5002);
        //print_r($obj->get());
        try {
            $obj->checkAccess();
        } catch (\Exception $e) {
            $this->assertEquals('You have no write access to object 5002', $e->getMessage());
        }
    }
    public function testCreateAndDelete(){
        error_reporting(E_ALL);

        $obj = new \BayCMS\Base\BayCMSObject($this->context);
        $_POST['uname']='benutzer';
        $_POST['de']='Stefan Holzheu';
        $_POST['en']='ÖÄU∑ <>?&;';
        $obj->set($_POST);
        $obj->addReference([5001],3);
        $id = $obj->save();
        $res=$obj->get();        
        $this->assertEquals('Stefan Holzheu',$res['de']);

        echo "Created object with id=$id\n";
        $this->assertGreaterThan(0, $id);
        
        $obj = new \BayCMS\Base\BayCMSObject($this->context);
        $obj->load($id);
        $res2=$obj->get();
        //print_r($res2);


        $this->assertEquals($_POST['en'],$res2['en']);
        $obj->setAccess([5408]);
        $this->assertEquals(0, $obj->numOtherOrgs());
        $obj->setAssign([5417]);
        $this->assertEquals(1, $obj->numOtherOrgs());
        $obj -> delete(true);
        $obj -> erase(true);
    }
}