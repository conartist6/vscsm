#include <stdlib.h>
#include <stdio.h>

int main(int argc, char *argv[]){
    char *words[] = {"zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten"};
    int i,j;
    for(j=0; j<50; j++){
    for(i=0; i<11; i++){
	printf("%s\n", words[i]);
	usleep(250000);
    }
    }
    while(1){
	sleep(1);
    }
    exit(0);
}
