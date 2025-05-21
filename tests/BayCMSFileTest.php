<?php

class BayCMSFileTest extends \PHPUnit\Framework\TestCase
{
    protected \BayCMS\Base\BayCMSContext $context;
    protected \BayCMS\Base\BayCMSContext $context_www;

    protected function setUp(): void
    {
        $this->context = new \BayCMS\Base\BayCMSContext(
            '/local/www/btineu',
            ['id' => 5001, 'id_benutzer' => 5002]
        );
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['PHP_SELF'] = '/btineu/index.php';
        $this->context_www = new \BayCMS\Base\BayCMSContext(
            '/local/www/btineu',
            ['id_benutzer' => 27724]
        );
    }


    public function testSave()
    {
        $file = new \BayCMS\Base\BayCMSFile($this->context);
        $v = [
            'name' => 'Regenwaage.png',
            'path' => 'inc',
            'de' => 'test-Datei',
            'source' => __DIR__ . '/data/Regenwaagen_mm.png',
            'description' => 'Test-File'
        ];
        $file->set($v);
        $id = $file->save();
        print "Created file with id=$id\n";
        $res = $file->get();
        $this->assertEquals($v['name'], $res['name']);

        $v['source'] = null;
        $v['name'] = 'Regenwaage MM.png';
        $v['path'] = 'admin';
        $v['add_id_obj'] = 1;
        $file->set($v);
        $file->save();


        $file->load($id);
        $v['de'] = 'Regenwaage vumulativ';
        $file->set($v);
        $file->save();

        $file->load($id);

        $res = $file->get();
        $this->assertEquals($v['de'], $res['de']);
        //print_r($res);
        $file->erase();
    }


    public function testZip()
    {
        $file = new \BayCMS\Base\BayCMSFile($this->context);
        $v = [
            'name' => 'Regenwaage.zip',
            'path' => 'inc/5001',
            'de' => 'test Zip',
            'extract' => 1,
            'source' => __DIR__ . '/data/Regenwaagen.zip',
            'description' => 'Test-File extracted'
        ];
        $file->set($v);
        $id = $file->save();
        print "Created file with id=$id\n";
        $res = $file->get();
        $this->assertEquals($v['de'], $res['de']);
        $file->erase();

        $v['extract'] = 0;
        $file->set($v);
        $id = $file->save();
        print "Created file with id=$id\n";
        $res = $file->get();
        $this->assertEquals($v['name'], $res['name']);
        $file->erase();


    }

}