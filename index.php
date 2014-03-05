<?php
	header('Content-Type: text/html; charset=utf-8');
	date_default_timezone_set('Europe/Berlin');
	DEFINE('DS', DIRECTORY_SEPARATOR);
	DEFINE('PATH', dirname(__FILE__));

	//user settings
	$database_name = 'database.sqlite';
	$overwrite_database = 0; //this overwrites the database file, if one is already present. caution!
	$stylesheet = 'style_classic.css'; // style_classic.css or style_zootool.css or really any CSS you want

	//automatic settings
	$console = array(
		'success' => array(),
		'notice' => array(),
		'error' => array()
	);
	$sqlite_support = (in_array('sqlite', PDO::getAvailableDrivers())) ? true : false;
	$zootool_export_filename = $_GET['import']; //needs to be the JSON format export, eg. "zoo_export_20140227.json"
	$is_import = (isset($_GET['import'])) ? true : false;

	if($overwrite_database === 1 && file_exists(PATH.DS.$database_name)) {
		unlink(PATH.DS.$database_name);
		$console['notice'][] = 'The database was completely erased due to the overwrite setting in the user settings.';
	}

	function zoo_insert($entries=array()) {
		global $db;
		global $console;
		global $items_auto_increment;
		if(empty($entries)) return false;

		//prepare the statement for the zoo entries
		$insert_items = "INSERT INTO items (id, title, url, type, public, referer, description, added, checksum, active) VALUES (:id, :title, :url, :type, :public, :referer, :description, :added, :checksum, :active)";
		$stmt_items = $db->prepare($insert_items);

		//prepare the statement for the tags
		$insert_tags = "INSERT OR IGNORE INTO tags (tag) VALUES (:tagname)";
		$stmt_tags = $db->prepare($insert_tags);

		//prepare the index table that links items and tags together
		$insert_links = "INSERT INTO items_to_tags (item_id, tag_id) VALUES (:item_id, :tag_id)";
		$stmt_link = $db->prepare($insert_links);

		//a statement that finds the ID of a specified tag (a last_insert_rowid replacement)
		$find_tag_id = "SELECT id FROM tags WHERE tag = :tagname";
		$stmt_find = $db->prepare($find_tag_id);



		foreach($entries as $item) {
			$success = false;

			if(empty($item['url'])) {
				$console['error'][] = 'You did not specify an URL to add';
				continue;
			}
			if(empty($item['title'])) {
				$console['error'][] = 'You did not specify a title for the URL '.$item['url'];
				continue;
			}

			try {
				$stmt_items->execute(array(
					':id' => $items_auto_increment,
					':title' => stripslashes($item['title']),
					':url' => stripslashes($item['url']),
					':type' => $item['type'],
					':public' => $item['public'],
					':referer' => stripslashes($item['referer']),
					':description' => stripslashes($item['description']),
					':added' => $item['added'],
					':checksum' => sha1($item['url']),
					':active' => 1
				));

				$success = true;

			} catch(PDOException $e) {
				$success = false;

				if(strval($e->getCode()) == 23000) {
					$console['notice'][] = 'This is already in your zoo';
				} else {
					$console['error'][] = 'Database error: '.$e->getMessage();
				}
			}
			if($success === true) $console['success'][] = 'Added "<a href="'.$item['referer'].'" class="button">'.stripslashes($item['title']).'</a>"';

			//process tags (todo: packs!)
			if(!empty($item['tags'])) {
				foreach($item['tags'] as $tag) {
					$tag_name = strtolower(trim($tag));

					//insert the tag
					$stmt_tags->execute(array(
						':tagname'=> stripslashes($tag_name)
					));

					//get the ID of the specified tag (bad performance?)
					$stmt_find->execute(array(
						':tagname'=> $tag_name
					));
					$insert_id_tags = array_shift($stmt_find->fetch(PDO::FETCH_ASSOC));

					//insert a DB entry that links the item to the tag
					$stmt_link->execute(array(
						':item_id'=> $items_auto_increment,
						':tag_id'=> $insert_id_tags
					));
				}
			}

			$items_auto_increment++;
		}

		return true;
	}


	if($sqlite_support) {

		try {

			$db = new PDO('sqlite:'.$database_name);
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			$db->exec('PRAGMA case_sensitive_like=OFF');

		} catch(PDOException $e) {
			echo($e->getMessage());
			exit('Could not establish database connection');
		}

		//check whether the required tables exist. if not, create the three
		//tables we need to manage the zoo (support for packs will need a fourth)
		try {
			$check = $db->query('SELECT id FROM items ORDER BY id DESC LIMIT 1');
			$items_auto_increment = array_shift(array_shift($check->fetchAll(PDO::FETCH_ASSOC)));
			$items_auto_increment++; //increment by 1 for next entry
		} catch(PDOException $e) {
			$console['notice'][] = 'The required database table ITEMS did not exist and had to be created.';
			$db->exec("CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT, url TEXT, type VARCHAR(16), added NUMERIC, checksum VARCHAR(64) UNIQUE, public BOOLEAN, referer TEXT, description TEXT, active BOOLEAN)");
			$items_auto_increment = 1;
		}
		try {
			$check = $db->query('SELECT 1 FROM tags LIMIT 1');
		} catch(PDOException $e) {
			$console['notice'][] = 'The required database table TAGS did not exist and had to be created.';
			$db->exec("CREATE TABLE tags (id INTEGER PRIMARY KEY, tag VARCHAR(64) UNIQUE)");
		}
		try {
			$check = $db->query('SELECT 1 FROM items_to_tags LIMIT 1');
		} catch(PDOException $e) {
			$console['notice'][] = 'The required database table that links TAGS to ITEMS did not exist and had to be created.';
			$db->exec("CREATE TABLE items_to_tags (item_id INTEGER, tag_id INTEGER)");
		}



		//CASE: IMPORT
		if($is_import && file_exists(PATH.DS.$zootool_export_filename)) {

			//load the content from the zootool export file
			$import = json_decode(file_get_contents(PATH.DS.$zootool_export_filename), true);
			if(empty($import)) {
				$db = null;
				exit('Nothing to import.');
			}

			zoo_insert(array_reverse($import));
		}



		//CASE ADD NEW URL
		if(isset($_GET['new']) || $_GET['new'] == 1) {
			//todo: add items!
			$insert = array(
				'title' => $_GET['title'],
				'url' => $_GET['url'],
				'referer' => $_GET['referer'],
				'type' => 'page',
				'public' => 'n',
				'description' => $_GET['description'],
				'added' => time(),
				'tags' => array_filter(array_map('trim', explode(',', $_GET['tags']))),
				'active' => 1
			);

			zoo_insert(array($insert));
		}



		//CASE SELECT
		if(isset($_GET['type']) || isset($_GET['term'])) {
			if($_GET['type'] == 'tag') {
				//tag search
				$searchterm = stripslashes($_GET['term']);
				$sql = 'SELECT i.*, t.tag FROM items i LEFT JOIN items_to_tags l ON i.id=l.item_id LEFT JOIN tags t ON l.tag_id=t.id WHERE i.id IN (SELECT l.item_id FROM tags t LEFT JOIN items_to_tags l ON t.id=l.tag_id WHERE t.tag = :searchterm) AND i.active = 1 ORDER BY i.added DESC';
			} else {
				//normal search
				$searchterm = '%'.stripslashes($_GET['term']).'%';
				$sql = 'SELECT i.*, t.tag FROM items i LEFT JOIN items_to_tags l ON i.id=l.item_id LEFT JOIN tags t ON l.tag_id=t.id WHERE title LIKE :searchterm AND i.active = 1 ORDER BY i.added DESC';
			}

			$db_result = array();
			try {
				$statement = $db->prepare($sql);
				$statement->execute(array(
					':searchterm' => strtolower($searchterm)
				));
				$db_result = $statement->fetchAll(PDO::FETCH_ASSOC);

				//group the results by item ID and clean up the tags (could be done using GROUP_CONCAT in the query in the future)
				$result = array();
				foreach($db_result as $item) {
					$id = $item['id'];
					if(!isset($result[$id])) {
						$result[$id] = $item;
						$result[$id]['tags'] = array();
					}
					$result[$id]['tags'][] = $item['tag'];
					unset($result[$id]['tag']); //delete the stray tag key
				}

			} catch(PDOException $e) {
				$console['error'][] = $e->getMessage();
			}


		}

		//close DB connection when we are done
		$db = null;
	} else {
		$console['error'][] = 'This application needs SQLite-support and PHP\'s PDO, but did not find it.';
	}

?><!DOCTYPE html>
<html>
<head>
	<title>Private Zoo</title>

	<meta name="viewport" content="width=device-width, initial-scale=1" />

	<link rel="stylesheet" href="<?= $stylesheet ?>" />
</head>
<body>
	<div id="container">

		<form id="search">
			<h1>Search your zoo</h1>

			<select name="type">
				<option value="searchterm"<?php if(isset($_GET['type']) && $_GET['type'] == 'searchterm') echo('selected') ?>>title</option>
				<option value="tag"<?php if(isset($_GET['type']) && $_GET['type'] == 'tag') echo('selected') ?>>tag</option>
			</select>
			<input type="text" name="term" value="<?php if(isset($_GET['term']) && !empty($_GET['term'])) echo(stripslashes($_GET['term'])) ?>" placeholder="search term or tag"<?php if(!isset($_GET['prefill'])) echo(' autofocus') ?> />
			<input type="submit" value="search" /> or <a href="#add" id="add-toggle" class="button">Add a new URL</a>
		</form>

		<form id="add"<?php if(isset($_GET['prefill'])) echo(' class="show"') ?>>
			<h1>Add a new URL</h1>

			<input type="hidden" name="new" value="1" />
			<input type="hidden" name="referer" value="<?php if(isset($_GET['prefill']) && !empty($_SERVER['HTTP_REFERER'])) echo(stripslashes($_SERVER['HTTP_REFERER'])) ?>" />
			<input type="text" name="title" placeholder="the title" value="<?php if(isset($_GET['title'])) echo(stripslashes($_GET['title'])) ?>"<?php if(isset($_GET['prefill'])) echo(' autofocus') ?> />
			<input type="text" name="url" placeholder="the url" value="<?php if(isset($_GET['url'])) echo(stripslashes($_GET['url'])) ?>" />
			<textarea rows="4" name="description" placeholder="the description"><?php if(isset($_GET['description'])) echo(stripslashes($_GET['description'])) ?></textarea>
			<textarea rows="2" name="tags" placeholder="the tags, comma separated"></textarea>
			<input type="submit" value="add to your zoo" />
		</form>

		<?php if(!empty($console['error']) || !empty($console['notice']) || !empty($console['success'])): ?>
		<div id="messages">
			<?php if(!empty($console['error'])): ?>
				<ul class="error">
					<?php foreach($console['error'] as $msg): ?>
					<li><?= $msg ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if(!empty($console['notice'])): ?>
				<ul class="notice">
					<?php foreach($console['notice'] as $msg): ?>
					<li><?= $msg ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if(!empty($console['success'])): ?>
				<ul class="success">
					<?php foreach($console['success'] as $msg): ?>
					<li><?= $msg ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<dl id="entries">

		<?php if(isset($result)): ?>
			<?php if(!empty($result)): ?>
			<?php foreach($result as $r): ?>
				<dt id="item-<?= $r['id'] ?>"><a data-timestamp="<?= $r['added'] ?>" href="<?= $r['url'] ?>"><?= $r['title'] ?></a></dt>
				<dd>
					<p class="url"><?= $r['url'] ?></p>
					<p class="desc"><time datetime="<?= date('Y-m-d\TH:i:s', $r['added']) ?>"><?= date('M d, Y', $r['added']) ?><?php if(!empty($r['description'])) echo(' &ndash;') ?></time> <?= $r['description'] ?></p>

					<ul class="tags"><?php
						foreach($r['tags'] as $tag) {
							$active = ($_GET['term'] == $tag) ? ' class="active"' : '';
							echo('<li><a href="?type=tag&term='.$tag.'"'.$active.'>'.$tag.'</a></li>');
						}
					?></ul>
				</dd>
			<?php endforeach; ?>
				<p><?= sizeof($result) ?> results in your zoo.</p>
			<?php else: ?>
				<p>No results in your zoo.</p>
			<?php endif; ?>
		<?php else: ?>
			<p>Enter a search term or tag to begin searching the <?= ($items_auto_increment-1) ?> items in your zoo. Or use the <a href="<?php

			//build a basic bookmarklet for collecting urls, remove newline, tabs and spaces
			echo(preg_replace('/[\r\n\t\s]*/', "", "javascript:
				(function (domain, t, u, d) {
					domain = '".((isset($_SERVER['HTTPS'])) ? 'https://' : 'http://' )."' + domain;
					d = (d && d[0]) ? d[0].getAttribute('content') : '';

					window.location.href = domain+'?prefill=1&title='+encodeURIComponent(t)+'&url='+encodeURIComponent(u)+'&description='+encodeURIComponent(d);

				}('".$_SERVER['HTTP_HOST'].rtrim($_SERVER['REQUEST_URI'], '/')."/', document.title, window.location, document.querySelectorAll('meta[name=\'description\'][content]')));
			"));

			?>" title="Drag this to your bookmarks bar" class="button">Lasso</a> to collect page links.</p>
		<?php endif; ?>
		</dl>
	</div>

	<script>
		window.onload = function() {
			document.getElementById('add-toggle').onclick = function(e) {
				e.preventDefault();
				document.getElementById('add').style.display = 'block';
			};
		};
	</script>
</body>
</html>