<?php

class newspaper_fulltext extends Plugin
	{
	private $host;
	function about()
		{
		return array(
			1.0,
			"Try to get fulltext of the article using newspaper python3 parser",
			"ds"
		);
		}

	function flags()
		{
		return array();
		}

	function init($host)
		{
		$this->host = $host;
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
		$host->add_filter_action($this, "action_inline", __("Inline content"));
		}

	function hook_prefs_tab($args)
		{
		if ($args != "prefFeeds") return;
		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"" . __('Newspaper_fulltext settings (newspaper_fulltext)') . "\">";
		print_notice("Enable the plugin for specific feeds in the feed editor.");
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();
		$enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
		$this->host->set($this, "enabled_feeds", $enabled_feeds);
		if (count($enabled_feeds) > 0)
			{
			print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";
			print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
			foreach($enabled_feeds as $f)
				{
				print "<li>" . "<img src='images/pub_set.png'
						style='vertical-align : middle'> <a href='#'
						onclick='editFeed($f)'>" . Feeds::getFeedTitle($f) . "</a></li>";
				}

			print "</ul>";
			}

		print "</div>";
		}

	function hook_prefs_edit_feed($feed_id)
		{
		print "<div class=\"dlgSec\">" . __("Newspaper") . "</div>";
		print "<div class=\"dlgSecCont\">";
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();
		$key = array_search($feed_id, $enabled_feeds);
		$checked = $key !== FALSE ? "checked" : "";
		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"newspaper_fulltext_enabled\"
			name=\"newspaper_fulltext_enabled\"
			$checked>&nbsp;<label for=\"newspaper_fulltext_enabled\">" . __('Get fulltext via newspaper parser') . "</label>";
		print "</div>";
		}

	function hook_prefs_save_feed($feed_id)
		{
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();
		$enable = checkbox_to_sql_bool($_POST["newspaper_fulltext_enabled"]) == 'true';
		$key = array_search($feed_id, $enabled_feeds);
		if ($enable)
			{
			if ($key === FALSE)
				{
				array_push($enabled_feeds, $feed_id);
				}
			}
		  else
			{
			if ($key !== FALSE)
				{
				unset($enabled_feeds[$key]);
				}
			}

		$this->host->set($this, "enabled_feeds", $enabled_feeds);
		}

	function hook_article_filter_action($article, $action)
		{
		return $this->process_article($article);
		}

	function process_article($article)
		{
		$url = $article['link'];
		$cmd = '/usr/bin/python3 /usr/share/nginx/tt-rss/plugins/newspaper_fulltext/np.py ' . $url;
    $cmd = <<<END
import sys
import newspaper
from newspaper import Config, Article
u = '$url'
a = Article(u, memoize_articles = False, keep_article_html = True, fetch_images = False)
a.download()
a.parse()
print (a.article_html)    
END;
		$output = escapeshellcmd('/usr/bin/python3 ' . $cmd);
		$out = exec($output, $res, $ret);
                $html = trim(implode($res));
                if ($html) {
                   $article["content"] = implode($res);
                }
		return $article;
		}

	function hook_article_filter($article)
		{
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) return $article;
		$key = array_search($article["feed"]["id"], $enabled_feeds);
		if ($key === FALSE) return $article;
		return $this->process_article($article);
		}

	function api_version()
		{
		return 2;
		}

	private
	function filter_unknown_feeds($enabled_feeds)
		{
		$tmp = array();
		foreach($enabled_feeds as $feed)
			{
			$result = db_query("SELECT id FROM ttrss_feeds WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
			if (db_num_rows($result) != 0)
				{
				array_push($tmp, $feed);
				}
			}

		return $tmp;
		}
	}
?>
