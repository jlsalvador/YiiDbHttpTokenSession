# YiiDbHttpTokenSession

YiiDbHttpTokenSession is a [Yii](http://www.yiiframework.com) extension that extends [CDbHttpSession](http://www.yiiframework.com/doc/api/1.1/CDbHttpSession) by using a database as a token-session linker for each request-response without the need of a cookie or PHPSESSID usage (like a Rest Api solution).

## How it works

The first time that you make a request and your application use a PHP session, a token will be generated and will be echoed in the HTTP header response like `Token: 12345678901234567890123456789012`.
Use this token in the next HTTP header request to continue the session, like `Token: 12345678901234567890123456789012`. You can use the HTTP parameter `_t` too (by default).
Each request generate a new Token that will be sent to the HTTP header response.


## Requirements

* PHP 5.3.0 (older versions untested)
* YiiFramework 1.1.13 (older versions untested)


## Install

1. Get the source in one of the following ways:
    * [Download](https://github.com/jlsalvador/YiiDbHttpTokenSession/releases) the lasted version and place the files under `protected/extensions/YiiDbHttpTokenSession/` under your application root directory.
    * Add this repository as a git submodule to your repository by calling under your application root directory:
      `git submodule add https://github.com/jlsalvador/YiiDbHttpTokenSession.git protected/extensions/YiiDbHttpTokenSession`

2. Edit your application configuration and set the session component to YiiDbHttpTokenSession:
```php
'components'=>array(
        'session'=>array(
            'class'=>'ext.YiiDbHttpTokenSession',
            'connectionID'=>'db', // Set the database Yii component, it's optional.
            'tokenRequestKeyName'=>'_t', // The $_REQUEST index name that will store a token id instead the HTTP header, defaults to '_t'.
            'tokenHeaderKeyName'=>'HTTP_TOKEN', // The $_SERVER index name that will store a token id, defaults to 'HTTP_TOKEN'.
            'tokenTimeout'=>1440, // The number of seconds after which data will be seen as garbage and cleaned up, defaults to 1440 seconds.
            'tokenTableName'=>'YiiToken', // The token table name, defaults to 'YiiToken'.
            'autoCreateTokenTable'=>true, // Whether the token DB table should be automatically created if not exists, defaults to true.
        ),
),
```

## Examples

JavaScript:
```javascript
var tokenId = '12345678901234567890123456789012'; // Set here your last token id from the HTTP header response.
$.ajax({
    beforeSend: function (xhr) {
        xhr.setRequestHeader('Token', tokenId);
    },
    url:'http://my-site.com/api/work',
    type:'GET',
    success:function(data) {
        console.log(data);
    },
    error:function (xhr, ajaxOptions, thrownError){
        console.log(xhr.responseText);
    }
}).then(function (data, textStatus, xhr) {
    tokenId = xhr.getResponseHeader('Token'); // This set the next token id for the next request.
});
```

CURL:
```shell
curl -i -H "Accept: application/json" -H "Token: 12345678901234567890123456789012" http://my-site.com/api/work
```


## Contributors

* [jlsalvador](https://github.com/jlsalvador)


## License

YiiDbHttpTokenSession is released under the [GNU Lesser General Public License](http://opensource.org/licenses/lgpl-license.php).
