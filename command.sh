#!/bin/bash
#author:NianBaoZou

phpunit="./vendor/bin/phpunit tests";
artisan="php artisan ";

echo "Laravel Commad Script";
echo "0. Create Test";
echo "1. Feature";
echo "2. Unit";
echo "3. Create Contoller";
echo "4. Create Model";
echo "5. Artisan Something";
echo "-------------------------------------------------------------------------------";
read NUM

case $NUM in
	5)
  	  echo 'Artisan Something';
	  echo 'Ex: db:seed or migrate or Another';
          read Artisan
	  $artisan $Artisan	  
	  ;;
	4)
 	  echo '0. Path: /';
	  echo '1. add migrate: -m';
       	  echo 'Input your create Model. add 0 or 1 if the Model need migrate or Path';
	  echo 'Ex: FolderName/NewModelName -m';
	  read NewModel
	  $artisan make:model $NewModel
	  ;;
	3)
 	  echo '0. Path: /';
	  echo '1. resource: --resource';
       	  echo 'Input your create Contorller. add 0 or 1 if the Ctl need CURD or Path';
	  echo 'Ex: FolderName/NewController --resource';
	  read NewController
	  $artisan make:controller $NewController
	  ;;
	2)	
	    echo 'Input your run Folder/UnitTest Name';
	    echo 'Ex: FolerName/TestName';
	    read UnitTestName
	    $phpunit/Unit/$UnitTestName
	  ;;
	1) 
	    echo 'Input your run Folder/FeatureTest Name';
	    echo 'Ex: FolerName/TestName';
	    read FeatureTestName
	    $phpunit/Feature/$FeatureTestName
	  ;;
	0) 
	    echo 'Input your need Create Test Class Name. add --unit if the Test is unit';
	    echo 'Ex: FolerName/TestName --unit';
  	    read CreateTestName	  
	    $artisan make:test $CreateTestName
	  ;;
esac
