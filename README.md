# dockerset  
Dockerの開発セット  
開発中  
  
## @Todo
WPインストール時の調整  
  
## 作成手順  
  
### .env編集  

**PHP_VERSION**
バージョン数値を入れる  
  
**WP_VERSION**  
"" : Wordpressを入れないPHP実行環境  
latest : 最新のWordpressをインストール
Version番号 : 指定のバージョンをインストール

**HOST_HTTP_PORT、 PHPMYADMIN_PORT**  
被らないポート番号を指定  
  
**DB関連**  
Wordpressを使う時は、  
DB_** を使用しインストールできます。  
  
DB_NAME以外を使う場合は、  
root  
password  

## 操作コマンド
| 操作    | コマンド                                                                                       |
| ----- | ------------------------------------------------------------------------------------------ |
| 起動    | `docker compose up -d`                                                                     |
| 停止    | `docker compose stop`                                                                      |
| 削除    | `docker compose down`                                                                      |
| 再ビルド  | `docker compose build --no-cache`                                                          |

## その他
Wordpress プラグインインストール時にFTP情報求められる可能性あります。
wp-config.phpに以下を追加
define('FS_METHOD', 'direct');