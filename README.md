# Private Zoo


A simple (and not very robust) PHP app that imports Zootool's JSON export files into a SQLite database and makes it searchable.

*A word of caution:* I built this for myself, so I didn't bother with a few things such as
- support for packs
- creating thumbnail images
- privacy settings, logins, etc.
- beauty (2 very basic stylesheets are supplied, "classic" and a Zootool-colored theme)
- performance
- scalability

This means I have not tested this with very large datasets. Your server may complain or time out during import, if you have a lot of items in your zoo.
I just wanted a way to continue to use the bookmarks I have amassed, combined with a simple search mechanism. If anyone wants to contribute, they're very welcome, but I cannot promise I'll keep actively developing this. I may.

### Requirements

- PHP5 with PDO/SQLite support
- .json file with [Zootool](http://zootool.com/) data export

### Instructions

- copy the files to your server (don't forget the .htaccess, as it protects your database and JSON file from download)
- take a look at the few configurable options in the first few lines in index.php (or keep the defaults)
- copy the JSON export to the same directory and note the filename
- call yourserver.com/private-zoo/?import=\<the JSON filename with extension\>
- wait a while for the import to finish
- go to yourserver.com/private-zoo/, start searching your archive or add new URLs
- (optional) drag the "Lasso" bookmarklet to make bookmarking even easier

If you would like to use pretty urls for the site:

- edit index.php and set *$pretty_urls* to 1
- edit the .htaccess file (may be invisible) and set *RewriteBase* to the path you're hosting the site in
- enjoy /search/foo/ and /tag/bar/


### Support my work

If you'd like to support what I do, please consider tipping.  
[![Gittip](https://dl.dropboxusercontent.com/u/19750980/logo_gittip.png)](https://www.gittip.com/oelna/)  [![Flattr](https://dl.dropboxusercontent.com/u/19750980/logo_flattr.png)](https://flattr.com/submit/auto?user_id=oelna&url=https%3A%2F%2Fgithub.com%2Foelna%2Fprivate-zoo&title=private-zoo&language=en&tags=github&category=software)

