dnl config.m4 for extension handlersocket

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl Check PHP version:

AC_MSG_CHECKING(PHP version)
AC_TRY_COMPILE([#include "php/main/php_version.h"], [
#if PHP_MAJOR_VERSION < 5 || (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 2)
#error  this extension requires at least PHP version 5.2.0 or newer
#endif
],
[AC_MSG_RESULT(ok)],
[AC_MSG_ERROR([need at least PHP 5.2.0 or newer])])

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

  PHP_INSTALL_HEADERS([ext/handlersocket], [php_handlersocket.h])
  PHP_NEW_EXTENSION(handlersocket, handlersocket.cc, $ext_shared)
fi
