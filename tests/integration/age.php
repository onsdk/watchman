<?php
/* Copyright 2014-present Facebook, Inc.
 * Licensed under the Apache License, Version 2.0 */

class ageOutTestCase extends WatchmanTestCase {
  function testAge1() {
    $dir = PhutilDirectoryFixture::newEmptyFixture();
    $root = realpath($dir->getPath());

    mkdir("$root/a");
    touch("$root/a/file.txt");
    touch("$root/b.txt");

    $this->watch($root);

    $this->assertFileList($root, array(
      'a',
      'a/file.txt',
      'b.txt'
    ));

    $res = $this->watchmanCommand('query', $root, array(
      'fields' => array('name', 'exists')
    ));
    $this->assertEqual(true, $res['is_fresh_instance']);
    $clock = $res['clock'];

    // Removing file nodes also impacts the suffix list, so we test
    // that it is operating as intended in here too
    $res = $this->watchmanCommand('query', $root, array(
      'expression' => array('suffix', 'txt'),
      'fields' => array('name'),
    ));
    $this->assertEqual(array('a/file.txt', 'b.txt'), $res['files']);

    unlink("$root/a/file.txt");
    rmdir("$root/a");

    $this->assertFileList($root, array('b.txt'));

    // Prune all deleted items
    $this->watchmanCommand('debug-ageout', $root, 0);

    // Wait for 'a' to age out and cause is_fresh_instance to be set
    $res = $this->waitForWatchman(
      array('query', $root, array(
        'since' => $clock,
        'fields' => array('name', 'exists')
      )),
      function ($list) {
        return idx($list, 'is_fresh_instance') === true;
      }
    );

    // Verify that the file list is what we expect
    $this->assertFileListUsingSince($root, $clock, array('b.txt'));

    // Add a new file to the suffix list; this will insert at the head
    touch("$root/c.txt");

    // suffix query to verify that linkage is safe
    $res = $this->watchmanCommand('query', $root, array(
      'expression' => array('suffix', 'txt'),
      'fields' => array('name'),
    ));
    sort($res['files']);
    $this->assertEqual(array('b.txt', 'c.txt'), $res['files']);
  }
}

// vim:ts=2:sw=2:et:

