dnl config.m4 for extension handlersocket

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl Check PHP version:

AC_MSG_CHECKING([PHP version])
tmp_version=$PHP_VERSION
if test -z "$tmp_version"; then
  if test -z "$PHP_CONFIG"; then
    AC_MSG_ERROR([php-config not found])
  fi
  php_version=`$PHP_CONFIG --version 2> /dev/null | head -n 1 | sed -e 's#\([0-9]\.[0-9]*\.[0-9]*\)\(.*\)#\1#'`
else
  php_version=`echo "$VERSION" | sed -e 's#\([0-9]\.[0-9]*\.[0-9]*\)\(.*\)#\1#'`
fi

if test -z "$php_version"; then
  AC_MSG_ERROR([failed to detect PHP version, please report])
fi

ac_IFS=$IFS
IFS="."
set $php_version
IFS=$ac_IFS
hs_php_version=`expr [$]1 \* 1000000 + [$]2 \* 1000 + [$]3`

if test "$hs_php_version" -le "5000000"; then
  AC_MSG_ERROR([You need at least PHP 5.0.0 to be able to use this version of HandlerSocket. PHP $php_version found])
else
  AC_MSG_RESULT([$php_version, ok])
fi

dnl If your extension references something external, use with:

PHP_ARG_WITH(handlersocket, for handlersocket support,
Make sure that the comment is aligned:
[  --with-handlersocket             Include handlersocket support])

dnl compiler C++:

AC_LANG([C++])

if test "$PHP_HANDLERSOCKET" != "no"; then
  dnl # check with-path
  SEARCH_PATH="/usr/local /usr"     # you might want to change this
  SEARCH_FOR="/include/handlersocket/hstcpcli.hpp"  # you most likely want to change this
  if test -r $PHP_HANDLERSOCKET/$SEARCH_FOR; then # path given as parameter
    HANDLERSOCKET_DIR=$PHP_HANDLERSOCKET
  else # search default path list
    AC_MSG_CHECKING([for hsclient files in default path])
    for i in $SEARCH_PATH ; do
      if test -r $i/$SEARCH_FOR; then
        HANDLERSOCKET_DIR=$i
        AC_MSG_RESULT(found in $i)
      fi
    done
  fi

  if test -z "$HANDLERSOCKET_DIR"; then
    AC_MSG_RESULT([not found])
    AC_MSG_ERROR([Please reinstall the hsclient distribution])
  fi

  dnl # add include path
  PHP_ADD_INCLUDE($HANDLERSOCKET_DIR/include)

  dnl # check for lib
  LIBNAME=hsclient
  AC_MSG_CHECKING([for hsclient])
  AC_TRY_COMPILE(
  [
    #include "handlersocket/hstcpcli.hpp"
  ],[
    dena::hstcpcli_ptr cli;
  ],[
    AC_MSG_RESULT(yes)
    PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $HANDLERSOCKET_DIR/lib, HANDLERSOCKET_SHARED_LIBADD)
    AC_DEFINE(HAVE_HANDLERSOCKETLIB,1,[ ])
    -L$HANDLERSOCKET_DIR/lib -lhsclient
  ],[
    AC_MSG_ERROR([wrong hsclient lib version or lib not found])
  ])

  PHP_SUBST(HANDLERSOCKET_SHARED_LIBADD)

  ifdef([PHP_INSTALL_HEADERS],
  [
    PHP_INSTALL_HEADERS([ext/handlersocket], [php_handlersocket.h])
  ], [
    PHP_ADD_MAKEFILE_FRAGMENT
  ])

  PHP_NEW_EXTENSION(handlersocket, handlersocket.cc, $ext_shared)
fi
