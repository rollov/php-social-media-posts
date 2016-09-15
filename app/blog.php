<?php
/**
 * Created by PhpStorm.
 * User: ror
 * Date: 30.08.16
 * Time: 10:29
 */

class Blog {

    public function index ($f3) {

        $feed = new \models\Feed($f3);

        $f3->set('posts', $feed->import(
            array(
                'facebook' => '1539466566349929',
                'twitter' => 'PixelCotton')
        ));
        $f3->set('content', 'ui/views/blog.htm');
        echo View::instance()->render('layout.htm');
    }
}