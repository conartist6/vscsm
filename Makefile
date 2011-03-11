all:
	gcc src/startserv.c -Wall -lconfig -lpthread -o bin/startserv

debug:
	gcc src/startserv.c -Wall -O0 -g -lconfig -lpthread -o bin/startserv

onetwo:
	gcc src/onetwo.c -Wall -o bin/onetwo
