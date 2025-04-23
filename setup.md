# BytePattern Setup

## Example Apache 2.4+ Configuration

    <VirtualHost *:80>
        ServerName example.com
        ServerAdmin user@example.com

        AddDefaultCharset UTF-8
        AddType text/cache-manifest .manifest

        # compress text, html, javascript, css, xml:
	    AddOutputFilterByType DEFLATE text/plain
	    AddOutputFilterByType DEFLATE text/html
	    AddOutputFilterByType DEFLATE text/xml
	    AddOutputFilterByType DEFLATE text/css
	    AddOutputFilterByType DEFLATE text/cache-manifest
	    AddOutputFilterByType DEFLATE application/xml
	    AddOutputFilterByType DEFLATE application/xhtml+xml
	    AddOutputFilterByType DEFLATE application/rss+xml
	    AddOutputFilterByType DEFLATE application/javascript
	    AddOutputFilterByType DEFLATE application/x-javascript

	    # the site never sits in docroot, very easy way to increase security
	    DocumentRoot "/path/to/www/pub"
	    ErrorLog "/path/to/www/log/error"
	    CustomLog "/path/to/www/log/access" common

	    <Directory "/path/to/www/pub">
	        Require all granted
	        Options ExecCGI IncludesNOEXEC MultiViews SymLinksIfOwnerMatch

	        # do not use if this is in .htaccess
	        AllowOverride None

	        # be careful with these as they could make the request execute twice
	        # ErrorDocument 404 /

	        <IfModule rewrite_module>
	            RewriteEngine on
	            RewriteBase /

	            # files in the pub directory will be served normally
	            RewriteCond %{REQUEST_FILENAME} !-d
	            RewriteCond %{REQUEST_FILENAME} !-f
	            RewriteCond %{REQUEST_FILENAME} !-l

	            # everything else gets filtered through pub/index.php for processing
	            RewriteCond $1 !^(index\.php) [NC]
	            RewriteRule ^(.*)$ /index.php/$1 [QSA,NC,L]
	        </IfModule>
	    </Directory>
	</VirtualHost>
