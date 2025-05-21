<?php

use function PHPUnit\Framework\assertCount;

class BayCMSImageTest extends \PHPUnit\Framework\TestCase {
    protected \BayCMS\Base\BayCMSContext $context;
    protected \BayCMS\Base\BayCMSContext $context_www;

    protected function setUp(): void {
        $this->context = new \BayCMS\Base\BayCMSContext('/local/www/btineu',['id'=>5001,'id_benutzer'=>5002]);
        $_SERVER['HTTP_HOST']='localhost';
        $_SERVER['PHP_SELF']='/btineu/index.php';
        $this->context_www = new \BayCMS\Base\BayCMSContext('/local/www/btineu',['id_benutzer'=>27724]);
    }
    public function testCreateUpdateDelete(){
        $image = new \BayCMS\Base\BayCMSImage($this->context);
        $v=['name'=>'Regenwaagen_mm.png',
        'source'=>__DIR__.'/data/Regenwaagen_mm.png',
        'de'=>'Regenwaagen',
        'height'=>500,
        'theight'=>100,
        'id_obj'=>5001];
        $image->set($v);
        $id=$image->save();
        print "Created Image with id=$id\n";
        $res=$image->get();
        $this->assertEquals('Regenwaagen',$res['de']);
        $this->assertEquals(500,$res['x']);

        $v=['de'=>'Regenwaagen mm','height'=>600];
        $image->set($v);
        $image->save();
        $res=$image->get();
        $this->assertEquals('Regenwaagen mm',$res['de']);
        $this->assertEquals(600,$res['x']);
        $v=['crop'=>2.0];
        $image->set($v);
        $image->save();
        $res=$image->get();
        $this->assertEquals(300,$res['y']);
        $image->erase();
    }

    public function testZip(){
        $image = new \BayCMS\Base\BayCMSImage($this->context);
        $v=['name'=>'Regenwaagen.zip',
        'source'=>__DIR__.'/data/Regenwaagen.zip',
        'height'=>500,
        'theight'=>100,
        'id_obj'=>5001];
        $image->set($v);
        $id=$image->save();
        $ids=$image->getIdsCreatedFromZip();
        $this->assertCount(2, $ids);
        foreach($ids as $id){
            print "Created Image with id=$id\n";
            $image->load($id);
            $res=$image->get();
            $this->assertEquals(500,$res['x']);
            $image->erase();
        }
        
    }


}