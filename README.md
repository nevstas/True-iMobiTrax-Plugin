# True iMobiTrax Plugin

**True iMobiTrax Plugin** для [iMobiTrax](https://www.imobitrax.com/) предназначен для составления блек и вайт листов площадок по заданным параметрам. Он должен заменить работу в Excel с отчетами iMobiTrax. Работа плагина проверялась только на iMobiTrax v3.8, на других версиях работоспособность не гарантируется.

Файл blacklist.php нужно закинуть в папку account, и в браузере открыть «путь_до_imobitrax/account/blacklist.php»

Допустим стоит задача отфильтровать площадки по следующим параметрам:
- Если слито 0.21$ и получено 0 кликов
- Если слито 0.10$ и CTR > 60%
- Если слито 1.00$ и ROI < 0

Выставляем эти параметры и получаем блеклист айдишников площадок, а также JavaScript код, который выполнив на странице статистики подсветит площадки. Для этого на странице статистики нажимаем F12, и в разделе Console вставляем данный код и выполняем.

#[Скачать](https://github.com/nevstas/True-iMobiTrax-Plugin/archive/master.zip)

![1](http://nevep.ru/screenshots/True_iMobiTrax_Plugin_-_2016-11-19_19.56.43.png)

![1](http://nevep.ru/screenshots/tli152yssl7bfahpqswo-2016-11-19_20-09-51.png)