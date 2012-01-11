#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <errno.h>
#include <unistd.h>
#include <fcntl.h>
#include <signal.h>
#include <pthread.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <libconfig.h>
#include <assert.h>
#include "vscsm.h"

console_server_list_t servlist;
const int d_buflines = 100, d_buflinlen = 100;
pthread_t iothread;
const char *pipepath, *lockpath;

/* Create memory for and set all defaults appropriately for a console_server_t. */
console_server_t* __console_server_t__(){
    console_server_t *cs;
    cs = (console_server_t*)malloc(sizeof(console_server_t));
    
    cs->nargs = -1;
    
    resetbuffer(cs);

    cs->gsn = NULL;
    cs->gamename = NULL;
    cs->gamedir = NULL;
    cs->gamecmd = NULL;
    cs->showtab = 0;

    cs->isrunning = 0;

    cs->rd_des = 0;
    cs->wr_des = 0;

    //Need to know here whether the server is running or not!
    //we will save some kind of a pid here if it is.

    return cs;
}

void resetbuffer(console_server_t* srv){
    srv->fmarker = -1;
    srv->lmarker = -1;
    srv->curline = 0;
    srv->linepos = 0;
    srv->lastunharmed = 0;
    srv->readingfrom = -1; 
}

/* Main. Forks a new process, sets new session id, redirects process IO to files, all in order to
 * daemonize this process. Signal handling is also set up in here. */
int main(){
    pid_t pid;
    pid_t sid;

    struct sigaction graceful, oldaction;

    pid = fork(); //forking here allows us to be a daemon! Its really for the sake of setsid.

    switch(pid){
	case -1:
	    printf("Could not fork!\n");
	    exit(3);                                            
	    break;
	case 0:
	    /*child*/
            //freopen("../vscsm2.log", "w", stdout);
	    freopen("/dev/null", "w", stdout); 
	    freopen("/dev/null", "r", stdin);
	    freopen("../vscsm.log", "w", stderr);    
	    
	    sid = setsid();
	    if(sid == -1){
         	fprintf(stderr, "Could not set SID! errno:%i\n", errno);
		fprintf(stderr, "pid: %i, gid: %i sid: %i\n", getpid(), getgid(), getsid(getpid()));
		fprintf(stderr, "%s\n", strerror(errno));

		exit(4);
	    }
	    fprintf(stderr, "pid: %i, gid: %i sid: %i\n",getpid(), getgid(), getsid(getpid()));
	    fflush(stderr);

	    graceful.sa_handler = terminatesig;
	    sigemptyset(&graceful.sa_mask);
	    graceful.sa_flags = 0x0;

	    sigaction(SIGINT, NULL, &oldaction);  //All signals lead to a graceful shutdown!
	    if(oldaction.sa_handler != SIG_IGN){  //Ha ha ha.
		sigaction (SIGINT, &graceful, NULL);
	    }
	    sigaction(SIGHUP, NULL, &oldaction);
	    if(oldaction.sa_handler != SIG_IGN){
		sigaction (SIGHUP, &graceful, NULL);
	    }
	    sigaction(SIGQUIT, NULL, &oldaction);
	    if(oldaction.sa_handler != SIG_IGN){
		sigaction (SIGQUIT, &graceful, NULL);
	    }
	    sigaction(SIGTERM, NULL, &oldaction);
	    if(oldaction.sa_handler != SIG_IGN){
		sigaction (SIGTERM, &graceful, NULL);
	    }
	    sigaction(SIGSEGV, NULL, &oldaction);
	    if(oldaction.sa_handler != SIG_IGN){
		sigaction (SIGSEGV, &graceful, NULL);
	    }
	    
	    /*This line calls the main loop
	     * everything above handles forking, redirection for this process,
	     * and other daemon-like behaviour for this program. */

	    child();
	    break;


 	default:    
	    /*parent*/
	    /*printf("pid: %i gid: %i sid: %i\n", getpid(), getgid(), getsid(getpid()));*/
	    exit(0);            
    }
    return 0;
}

int child(){
    /*Read servers config here!*/
    config_t cfg;
    config_init(&cfg);

    if(! config_read_file(&cfg, CONFIG_FILE_PATH)){
	fprintf(stderr, "%s:%d - %s\n", config_error_file(&cfg),
		config_error_line(&cfg), config_error_text(&cfg));
	config_destroy(&cfg);
	terminate();
    }

    DBGOUT("parsed config!")

    config_setting_t *cfg_set;

    cfg_set = config_lookup(&cfg, "global");
    config_setting_lookup_string(cfg_set, "pipepath", &pipepath);
    config_setting_lookup_string(cfg_set, "lockpath", &lockpath);

    fprintf(stderr, "Pipe path: %s\nLock path: %s\n", pipepath, lockpath), fflush(stderr);

    cfg_set = config_lookup(&cfg, "servers.slist");
    servlist.size = config_setting_length(cfg_set);

    int i;
    servlist.list = (console_server_t**)malloc(servlist.size * sizeof(console_server_t*));

    for(i=0; i<servlist.size; i++){
	config_setting_t *cfg_server = config_setting_get_elem(cfg_set, i); 

	servlist.list[i] = __console_server_t__();
	console_server_t *srv = servlist.list[i]; 

	config_setting_lookup_string(cfg_server, "shortname", &srv->gsn);
       	config_setting_lookup_string(cfg_server, "fullname", &srv->gamename);
	config_setting_lookup_string(cfg_server, "gamedir", &srv->gamedir);
	config_setting_lookup_string(cfg_server, "gamecmd", &srv->gamecmd);
	config_setting_lookup_int(cfg_server, "showtab", &srv->showtab);
	
        //fprintf(stderr, "%s\n", srv->gamename), fflush(stderr);

        if(!config_setting_lookup_int(cfg_server, "bufferlines", &srv->buf.lines)){
	    srv->buf.lines = d_buflines;
	}
	if(!config_setting_lookup_int(cfg_server, "bufferlinelength", &srv->buf.linlen)){
	    srv->buf.linlen = d_buflinlen;
	}

	srv->buf.buf = (line_t*)malloc(srv->buf.lines * sizeof(line_t));
	int j;
	for(j=0; j<srv->buf.lines; j++){
	    srv->buf.buf[j].line = (char*)malloc(srv->buf.linlen * sizeof(char));
	}

    }

    DBGOUT("Parsed all server options for all servers!")	
    
    FILE *lockfile;
    lockfile = fopen(lockpath, "w");
    if(lockfile == NULL){
	fprintf(stderr, "Could not open lockfile!\n");
	terminate();
    }
    fprintf(lockfile, "%i\n", getpid());
    fclose(lockfile);    
    
    //startServer(servlist.list[0]);
    
    //no point in listening until its possible something is signaling

    pthread_attr_t *threadatt = (pthread_attr_t*)malloc(sizeof(pthread_attr_t));
    pthread_attr_init(threadatt);
    pthread_create(&iothread, threadatt, &ioHandler, NULL);
    free(threadatt);

    console_server_t *srv;
    while(1){
	static int nawake;
	nawake = 0;
	for(i=0; i<servlist.size; i++){
	    srv = servlist.list[i];
	    if(srv->isrunning){
		char tbuf [10];
		ssize_t charsRead = read(srv->rd_des, tbuf, 10);
		if (charsRead > 0){
		    lineBufferFragAdd(srv, tbuf, charsRead);
		    nawake++;
		}
		else if(charsRead == 0){
		    //DO NOTHING. FASTER.
		}
		else if(charsRead == -1){
		    if(errno == EAGAIN){
			//no-op
		    }
		    else{
			fprintf(stderr, "errno: %i \nstrerror: %s\n", errno,
				strerror(errno));
			fflush(stderr);
		    }
		}
		else{
		    srv->isrunning = 0;
		    fprintf(stderr, "FAIL\n");
		    fflush(stderr);
		    //printf("break condition: charsRead=%i errno=%i\n", charsRead, errno);
		    break;
		}
		errno = 0;
	    }
	}
	//If all servers were silent sleep for a little bit.
	if(nawake == 0) usleep(250000);	
    }
    	
    terminate();
}

void lineBufferFragAdd(console_server_t *srv, char *tbuf, int linelen){
    int nli = -1; /*newline integer*/
    const char *nlp = NULL; /*newline pointer*/

    /* Loop through string: find newline */

    nlp = strnchr(tbuf, '\n', linelen);
 
    if(nlp == NULL){
	lineBufferAdd(srv, tbuf, linelen);
    }
    else{
	nli = nlp-tbuf;
	/*Copy to a temporary array or else appending a string terminator
	 * will write over the first character of the next line*/
	char *temparr = (char*)calloc((nli+1), sizeof(char));
	strncpy(temparr, tbuf, nli+1);

	lineBufferAdd(srv, temparr, nli+1);/*Add up to and including the newline*/

	if(nli+1 != linelen){
	    /*add only if a newline is not the last character*/
	    lineBufferFragAdd(srv, tbuf+nli+1, linelen - nli - 1); /*recurse*/
	}
    }
}

void lineBufferAdd(console_server_t *srv, char *tbuf, int fraglen){
    int tamnt; /*truncation amount*/

    //wait until this line is not being used by a printing function
    while(srv->readingfrom == srv->curline); //spinlock!   

    tamnt = srv->buf.linlen - srv->linepos - fraglen - 1;
    //fprintf(stderr, "linlen: %i, linepos: %i, fraglen:%i, tamnt: %i\n",
    //        srv->buf.linlen, srv->linepos, fraglen, tamnt), fflush(stderr);
    if(tamnt < 0){
	char ttbuf[11];
	strncpy(ttbuf, tbuf + fraglen + tamnt - 1, -tamnt + 1);
	tbuf[fraglen + tamnt - 1] = '\n';
	fraglen += tamnt; /*since tamnt is neg*/
	
	strncpy(&srv->buf.buf[srv->curline].line[srv->linepos],tbuf, fraglen);
	/*move non-truncated part from temporary array to actual line buffer*/
	srv->buf.buf[srv->curline].line[srv->linepos + fraglen] = 0;
	incrementCurLine(srv);
        /*strncpy(&lb[curline][linepos], "\32\32\32\32\32", 5);
        linepos += 5;*/	
	//strncpy(&lb[curline][linepos], ttbuf, -tamnt);
	strncpy(&srv->buf.buf[srv->curline].line[srv->linepos],ttbuf, -tamnt); 
	srv->linepos -= tamnt;	
    }    
    else{
	//strncpy(&lb[curline][linepos], tbuf, fraglen);
	strncpy(&srv->buf.buf[srv->curline].line[srv->linepos],tbuf, fraglen); 
	/*move from temporary array to actual line buffer*/
	srv->linepos += fraglen; 
    }

    /*if(lb[curline][linepos-1] == 10){ //equiv of code below
	lb[curline][linepos] = 0;
	incrementCurLine();
    }*/

    if(srv->buf.buf[srv->curline].line[srv->linepos - 1] == '\n'){
	srv->buf.buf[srv->curline].line[srv->linepos] = 0;
        incrementCurLine(srv);
    }	
}

void incrementCurLine(console_server_t *srv){
    /*always do this*/
    int test, i = 0;
    char* l;
    l = srv->buf.buf[srv->curline].line;
    while(1){
	if(l[i] == '\0'){
	    break;
	}
	//
	//test = (l[i] == '\n' && l[i+1] == '\n');
	//assert(!test); //this assertion COULD fail accidentally I think, but its improbable.
	i++;
    }
    srv->curline++;
    srv->linepos = 0;
    
    if(srv->fmarker-1 == srv->lmarker){
        srv->fmarker++; 
    }
    else if(srv->fmarker == 0 && srv->lmarker >= srv->buf.lines - 1){
	srv->fmarker = 1;
    }	                 

    srv->lmarker = srv->curline - 1;
    /*now updated to current state*/

    if(srv->fmarker == -1){
	srv->fmarker = srv->lmarker;
    }
    if(srv->curline >= srv->buf.lines){
	srv->curline = 0;
    }
    else if(srv->lmarker >= srv->buf.lines){
	srv->lmarker = 0;
    }
    if(srv->fmarker >= srv->buf.lines){
	srv->fmarker = 0;
    }
    /*DONE: Cover case in which fmarker should be set to LBLEN*/
    /*DONE: roll over if buffer length is too long.*/

    if(srv->curline == srv->lastunharmed){
	srv->lastunharmed++;
    }
    if(srv->lastunharmed >= srv->buf.lines){
	srv->lastunharmed = 0;
    }
}

int startServer(console_server_t *srv){
    /* Server already has all configuration data loaded and line buffer created. Just need to run the command
     * and register with the manager that the server is running (its pid)
     */
    //srv->isrunning = 1;
    if(srv->isrunning){
	return ERR_START_ALREADY_RUNNING;
    }
    
    chdir(srv->gamedir);
    DBGOUT("changed dir")

    char *tpos, *cmdtemp;
    int tposn, slen, i;
    char **gamecmd;//An array of pointers to the real stuff which is stored in the temp var.

    slen = strlen(srv->gamecmd);
    cmdtemp = (char*)malloc((slen + 2)* sizeof(char));
    strcpy(cmdtemp, srv->gamecmd);

    fprintf(stderr, "Starting: %s\n", cmdtemp),fflush(stderr); 
	
    //if unknown get number of args in command
    if(srv->nargs == -1){
	srv->nargs = 0;
	tpos = cmdtemp;
	while(1){
	    tpos = strchr(tpos, ' ');
	    //DBGOUT("not still alive.")
	    if(tpos){
		srv->nargs++;
	    }
	    else break;
	    tpos++;
	}
    }//No spaces, no arguments. Add one for the base command plz.
    
    fprintf(stderr, "Nargs: %i\n", srv->nargs), fflush(stderr); 

    gamecmd = (char**)malloc((srv->nargs + 2) * sizeof(char*));
    //plus 2 - one for the base command and one for the terminating 0    

    tpos = cmdtemp; //Assign temp posn to start of the string 
    
    i=0;
    do{
    //DBGOUT("loop")
    gamecmd[i] = tpos;		 //Beginning of a word.
    tpos = strchr(tpos, ' ');    //pointer to end of a word.
    if(!tpos) break;	         //no space found (last word)
    tposn = tpos - cmdtemp + 1;	 //index of end of word
    *tpos = 0;			 //Null terminate this section of string.
    tpos++;			 //Start of next word. Rinse, repeat.  
    i++;
    //fprintf(stderr,"i:%i, %i < %i?\n", i, tposn, slen),fflush(stderr);
    }while(tposn < slen);	 //Not sure if this is necessary but it can't hurt O_o.
    
    //fprintf(stderr, "i: %i\n", i),fflush(stderr); 
        			
    gamecmd[i+1] = '\0';		 //i/tpos not ++'d if no space found.
				 //trailing space could be a problem...?

    int cpid;
    cpid = rwPOpen(gamecmd, &srv->rd_des, &srv->wr_des);
    fcntl(srv->rd_des, F_SETFL, fcntl(srv->rd_des, F_GETFL) | O_NONBLOCK);

    fprintf(stderr, "Spawned pid %i.\n", cpid),fflush(stderr);
    srv->isrunning = cpid;

    free(cmdtemp);
    free(gamecmd);
    return 0;
}

int stopServer(console_server_t *srv){
    if(srv->isrunning){//if running		    
	close(srv->rd_des);//close descriptors.
	close(srv->wr_des);
	fprintf(stderr, "Killing process group %i.\n", srv->isrunning), fflush(stderr); 
	kill(0 - srv->isrunning, SIGTERM);
	int status;
	waitpid(srv->isrunning, &status, 0);
	fprintf(stderr, "Process %i exited with status %i\n", srv->isrunning, status), fflush(stderr);
	srv->isrunning = 0; //issue group kill to neg pid of process group of leader.
	
	resetbuffer(srv);
		
	return ERR_SUCCESS;
    }
    else return ERR_STOP_NOT_STARTED;//server not running. 
}

void* ioHandler(void* t){
    //We will always be receiving input of some sort SO.
    while(1){
	int pipedes, instbufsz = 401;
	int i, cpos = -1;
	errno = 0;

 	static char *instruction;
	instruction = (char*)malloc(instbufsz * sizeof(char));
	//we will need to realloc if we come within 8 chars of this limit so lets make it pretty big!

	//DBGOUT("blocking for next instruction")

	//fprintf(stderr, "%s\n", pipepath), fflush(stderr);
	pipedes = open(pipepath, O_RDONLY); //blocking open
	if(!pipedes){
	    DBGOUT(strerror(errno))
	}
	
	//DBGOUT("received instruction")

	while(1){
	    char tbuf [9];
	    ssize_t charsread = read(pipedes, tbuf, 8);
	    tbuf[charsread] = 0;
	    if (errno == EAGAIN && charsread == -1){
		break;     
	    }
	    else if (charsread > 0){
		strncpy(instruction + cpos + 1, tbuf, charsread);
		cpos += charsread;
		//printf("%s", tbuf);
	    }
	    else{
		/*printf("break condition: charsread=%i errno=%i\n", charsread, errno);*/
		break;
	    }

	    if(cpos >= instbufsz - 9){ //stay null terminated plz.
		fprintf(stderr, "I find it unlikely that the instruction buffer should have needed"
			"to be that large...\n");
		fflush(stderr);
		instruction = realloc(instruction, instbufsz * 2 * sizeof(char));
		instbufsz = 2 * instbufsz; 
	    } 

	    errno = 0;
	}

	close(pipedes);    

	char opcode = instruction[0];

	//fprintf(stderr, "Processing instruction %c.\n", opcode),fflush(stderr);

	FILE *pipe; 
	errno=0;   
	
	//while((pipeint = open(pipepath, O_WRONLY | O_NONBLOCK)) == -1){}

	switch(opcode){
	    //opcodes:
	    // 'p': _P_rint new output for all servers
	    // 'a': full buffer print for _A_ll servers
	    // 'c': recieve _C_ommand
	    // 's': _S_tart a server
	    // 'o': st_O_p a server
	    // 'l': _L_ist running servers
	    // 'f': _F_ull list of running servers, including names and other info which the
	    //		webUI may need to be properly configured.
	    // 'h': HUP -- reload the config file
	    // 'r': _R_egister a new web console.
	    case 'p':
		{ 
		    //if running, prefix line like p@css 13 cod 2@
		    pipe = fopen(pipepath, "w");

		    fprintf(pipe, "p@");
		    char sp[] = {'\0', '\0'};
		    static int foundany;
		    foundany = 0;
		    for(i=0; i<servlist.size; i++){
			static console_server_t *s;
			s = servlist.list[i];
			if(s->isrunning){	
			    fprintf(pipe, "%s%s %i", sp, s->gsn,
				(s->fmarker <= s->lmarker) ?
				(s->fmarker == -1)?0:s->lmarker - s->fmarker + 1: 
				s->buf.lines + s->lmarker - s->fmarker + 1); 
			    sp[0]=' ';

			    foundany = 1;
			}
		    }
		    if(!foundany) break; //break without printing a newline, ex: p@
		    fprintf(pipe, "\n"), fflush(pipe);
		    foreachrunningserver(printBuffer, (void*)pipe);
		}
		break;
	    case 'f':
		{
 		    pipedes = open(pipepath, O_WRONLY);   
		    pipe = fdopen(pipedes, "w");

		    fprintf(pipe, "f@\n");
 		    for(i=0; i<servlist.size; i++){
			static console_server_t *s;
			s = servlist.list[i];
			if(s->showtab){
			    fprintf(pipe, "%s\n", s->gsn);
			    if(s->gamename){
				fprintf(pipe, "%s\n", s->gamename);
			    }
			}
		    }        	    
		}
		break;
	    case 'c':
		{
 		    fprintf(stderr, "Processing instruction %c.\n", opcode),fflush(stderr);  
 		    console_server_t *s = NULL;
		    char *endgn, *endcmd;
		    int j, msgint;
		    //find address of @, this is game name. Start game with that name.
		    for(i=2; i<instbufsz; i++){
			switch(instruction[i]){
			    case '\0':
				pipe = fopen(pipepath, "w"); 
				fprintf(pipe, "%s", errormsgs[ERR_INVALID_COMMAND]);
				break;
			    case '@':
				endgn = &instruction[i];
				goto fingn;
			}
		    }
fingn:		    
 		    for(i = (endgn - instruction) + 1; i<instbufsz; i++){
			switch(instruction[i]){
			    case '@':
				instruction[i] = '\0';
			    case '\0':
				endcmd = &instruction[i];
				goto fincmd;
			}
		    }
fincmd:		    
		    for(j=0; j<servlist.size; j++){
			if(!strncmp((const char*)instruction + 2,
				    (const char*)servlist.list[j]->gsn,
				    (int)(endgn - instruction - 2))){
			    s=servlist.list[j];
			    break; 
			}
		    }
 		    if(s){
			int rv = 0, written = 0;
			while(rv != -1 && written < endcmd - endgn){
			    rv = write(s->wr_des, endgn + 1 + written, endcmd - endgn - written);
			    if(rv != -1){
				written += rv;
				fprintf(stderr, "wrote %i out of %i\n", written,
					endcmd - endgn),fflush(stderr);
			    }
			}
			rv = write(s->wr_des, "\r\n", 2);
			if (rv == -1){
			    msgint = ERR_SERVER_WRITE_ERROR;
			}
			else msgint = ERR_SUCCESS;
			//msgint = 0;
			pipe = fopen(pipepath, "w"); 
		       	fprintf(pipe, "%s", errormsgs[msgint]); 	
		    }
		    else{
			pipe = fopen(pipepath, "w");   
			fprintf(pipe, "%s", errormsgs[ERR_NO_SUCH_SERVER]);   
		    } 
		}
		break;
	    case 's':
		{   
 		    fprintf(stderr, "Processing instruction %c.\n", opcode),fflush(stderr);  
		    //DBGOUT("Opcode: s")
		    console_server_t *s = NULL;
		    char *endgn;
		    int j, msgint;
		    //find address of \0 or @, this is game name. Start game with that name.
		    for(i=2; i<instbufsz; i++){
			if(instruction[i] == '\0' || instruction[i] == '@'){
			    instruction[i] = '\0';
			    endgn = &instruction[i];
			    break;
			}
		    }
		    //DBGOUT(instruction[2])
		    for(j=0; j<servlist.size; j++){
			if(!strncmp((const char*)instruction + 2,
				    (const char*)servlist.list[j]->gsn,
				    (int)(endgn - instruction - 2))){
			    s=servlist.list[j];
			    break; 
			}
		    }
 		    if(s){
			msgint = startServer(s);
			//msgint = 0;
			pipe = fopen(pipepath, "w"); 
		       	fprintf(pipe, "%s", errormsgs[msgint]); 	
		    }
		    else{
			pipe = fopen(pipepath, "w");   
			fprintf(pipe, "%s", errormsgs[ERR_NO_SUCH_SERVER]);   
		    } 

		}
		break;
	    case 'o':
		{
  	     	    fprintf(stderr, "Processing instruction %c.\n", opcode),fflush(stderr);   
		    console_server_t *s = NULL;
                    char *endgn;
		    int j;

 		    for(i=2; i<instbufsz; i++){
			if(instruction[i] == '\0' || instruction[i] == '@'){
			    instruction[i] = '\0';
			    endgn = &instruction[i];
			    break;
			}
			
		    } 
		    //get server from short name
                    for(j=0; j<servlist.size; j++){
			if(!strncmp((const char*)instruction + 2,
				    (const char*)servlist.list[j]->gsn,
				    (int)(endgn - instruction - 2))){
			     s = servlist.list[j]; 
			     break; 
			}
		    } 
                    pipe = fopen(pipepath, "w");
		    if(s){
			fprintf(pipe, "%s", errormsgs[stopServer(s)]);
		    }
		    else{
			fprintf(pipe, "%s", errormsgs[ERR_NO_SUCH_SERVER]);
		    }
		}
		break;
	    case 'l':
		{
		    pipedes = open(pipepath, O_WRONLY);   
		    pipe = fdopen(pipedes, "w");

		    fprintf(pipe, "l@");
                    char sp[] = {'\0', '\0'};
 		    for(i=0; i<servlist.size; i++){
			static console_server_t *s;
			s = servlist.list[i];
			if(s->isrunning){
			    fprintf(pipe, "%s%s", sp, s->gsn);
			    sp[0]=' ';
			}
		    }
		}
		break;
	    default:
 		    pipedes = open(pipepath, O_WRONLY);   
		    pipe = fdopen(pipedes, "w");
		    fprintf(pipe, "%c@Unknown Command!", opcode);
		break;
	}

	fclose(pipe);	    

	if (instbufsz > 401){
	    instruction = realloc(instruction, 401 * sizeof(char));
	    //deallocate extra instruction buffer space after reading extra-long instruction.
	}
    }    
}

int printBuffer(console_server_t *srv, void *arg){
    int i; 
    FILE *pipe = (FILE*)arg;
    
    if(srv->isrunning){
	if(srv->fmarker == -1){
	    return;
	}
	if(srv->fmarker <= srv->lmarker){ 
	    for(i=srv->fmarker; i<=srv->lmarker; i++){
		srv->readingfrom = i;
		fprintf(pipe, "%s<br />", srv->buf.buf[i].line);
	    }
	}
	else{
	    for(i=srv->fmarker; i<srv->buf.lines; i++){
		srv->readingfrom = i;
		fprintf(pipe, "%s<br />", srv->buf.buf[i].line);
	    }     
	    for(i=0; i<=srv->lmarker; i++){
		srv->readingfrom = i;
		fprintf(pipe, "%s<br />", srv->buf.buf[i].line);
	    }     
	}
	srv->fmarker = srv->lmarker = srv->readingfrom = -1;
    }
    return 0;
}

void foreachserver(int (*run)(console_server_t*, void*), void* arg){
    int i;
    for(i=0; i<servlist.size; i++){
	if(arg == NULL){
	    ((int(*)(console_server_t*))run)(servlist.list[i]);
	}
	else{
	    run(servlist.list[i], arg);
	}
    }
}

void foreachrunningserver(int (*run)(console_server_t*, void*), void* arg){
    int i;
    for(i=0; i<servlist.size; i++){
	if(servlist.list[i]->isrunning){
	    if(arg == NULL){
		((int(*)(console_server_t*))run)(servlist.list[i]);
	    }
	    else{
		run(servlist.list[i], arg);
	    }
	}
    }   
}

const char* strnchr(const char *str, int character, size_t searchBytes){
        int i;
        for(i=0; i<searchBytes; i++){
                if(str[i] == character){
                        return &str[i];
                }
        }
        return NULL;
}

int rwPOpen(char *const argv[], int *rd, int *wt){
    pid_t pid;     
    int p[4];
    /*		    output PIPE	input PIPE
     * Parent end   0 - read  	3 -write
     * Child end    1 - write  	2 -read
     */
    
    if(pipe(p) == -1){
	DBGOUT("ERRAWWR")
    }
    if(pipe(p+2) == -1){
	DBGOUT("ERRAWWR")
    }
    pid = fork(); 
    switch(pid){
	case -1: /*fork has failed*/
	    printf("popen's fork call has failed\n");
	    
	    close(p[0]), close(p[1]);
	    close(p[2]), close(p[3]);
	    break;
	case 0:  /*child process*/
	    close(p[0]), close(p[3]);	    
	    
            //DBGOUT("Forking streams");

	    if (dup2(p[1], STDOUT_FILENO) == -1){
	        DBGOUT("failed duping onto stdout");
	    }
	    if (dup2(p[1], STDERR_FILENO) == -1){
	        DBGOUT("failed duping onto stderr");
	    }
	    if (dup2(p[2], STDIN_FILENO) == -1){
	    }

	    close(p[1]);
	    close(p[2]);

	    //dup2(STDIN_FILENO, STDERR_FILENO); //Merge stdout and stderr.
            //freopen("/dev/null", "w", stderr); //or just close stderr. This should be an option!
            //otherwise this process still shares stderr with its parent! 
            //Deprecated by above dup2

	    execvp(*argv, argv);
	    exit(0);
	    break;
	default: /*parent*/
	    /*printf("%s\n", pid);*/
		
 	    close(p[1]), close(p[2]);
	    *rd = p[0];
	    *wt = p[3];
	    
	    setpgid(pid, 0);

	    return pid;  
    }
    return -1; //(error)
}

void terminatesig(int signal){
    fprintf(stderr, "vscsm terminated via signal %i.\n", signal),fflush(stderr);
    terminate();
}

void terminate(){
    unlink(lockpath);
    foreachrunningserver((int (*)(console_server_t*, void*))stopServer, NULL);
    exit(0);
}

/* DONE: Make input buffer roll over from BUFLEN to 0
 * DONE: LOCK file with current process ID
 * DONE: Make output function write to named pipe.
 * DONE: PHP script to send SIGIO and collect results
 * DONE: Get rid of deprecated signal() call
 * DONE: Make sure stuff is interrupt safe.
 * DONE: Find out why program occasionally exits without error
 *	    answer: interrupts don't like being interrupted
 *	    but: it broke again! :(
 *	    AHA!: it has something to do with the number of people who have
 *	    the pipe open for reading already...
 *	    Fixed it with all the other crap.
 * DONE: Why does PHP script occasionally just hang
 * DONE: clear lock file on unusual (or any) shutdown, cause PHP
 *	    to do nothing if the lock file does not exist
 * DONE: avoid hanging AJAX requests (make async)
 * DONE: avoid echoing partial lines from buffer... >:(
 * DONE: Things being lost...
 * BUGFIXED: If scrollback is not cleared before first rollover, prints from 0
 *	    to end of input.
 * BUGFIXED: Random single ascii characters often end up after a space at the
 *	    end of lines O_o
 * BUGFIXED: A LOT of newlines are printed before the first real server output.
 * BUGFIXED: Sometimes extra whitespace is printed.
 * BUGFIXED: First request does not get from start of server anymore...
 * BUGFIXED: Ensure that nothing crazy happens on empty requests.
 * DONE: N/A now: Make sure that the lineserver terminates if the underlying server does.
 * DONE: Make sure that ANY sort of termination
 *	a) also terminates related processes
 *	b) closes out the lock file.
 * DONE: Fix multiline statements missing chunks.
 * DONE: Implement command input
 * WONTFIX: Javascript should have scrollback of FIXED length.
 * WONTFIX YET: Allow web interface to collect additional information:
 *	Number of players, server IP address, current map, hlstatsx running...
 * DONE: Multiple servers!
 * DONE: System load stats.
 * DONE: Make resizing JS execute on resize.
 * LATER FEATURE: Integrate SteamCondenser (maybe)
 *	    this might be a huge "plugins" project
 * DONE: Add global fds for interrupt code to avoid repeated fs access
 * DONE: Better and more uniform strategy for PHP scripts to be called to
 *	perform any given action
 * DONE: Group secondly ajax transactions into one
 * DONE: unify server manager into one prog
 *
 * TODO BUG?: MasterRequestRestart line is prefixed with some sort of illegal character!
 * TODO: Old spans are NOT culled right now
 * TODO FEATURE: Implement command search/cacheing
 * TODO: Move todos somewhere else.
 * TODO: Security audit
 * TODO BUG: Make sure page stays within boundaries...
 *	    still some weird thing under linux with command input box... :/
 * TODO: Add some some sort of settings window such that the settings used for each
 *	server originate in the web interface and are propogate smoothly
 * TODO: Password protection! :o
 * TODO: Allow client to send single full buffer to server on startup instead of only update
 * TODO: Muliple WebUI's either prevented or run properly.
 *	to run properly we need separate info for each webUI, maybe some sort of authentication sequence?
 * TODO: Password protection for webUI
 * TODO: webUI configuration interface
 *	    would be wise to force version compatibility check
 * TODO BUG: Output from multiple servers not properly parsed into webUI boxes 
 */

/*
 * TODOs for new backend multiserver support:
 * DONE This app must have a list of which servers are running and which servers are not.
 * DONE This app must have a function to start/stop(!) a server given a linebuffer/conf object
 * DONE server stopping function.
 * DONE This app must have a loop which for each running server collects output.
 * DONE Need to implement last untouched.
 * DONE BUG: Stderr for forked process still mixed with stderr for server process
 * Thought: A game server can only be started if the console server manager starts it.
 *	The csm cannot reconnect to a server that it has 'lost'.
 *	Therefore: this manager can simply keep track of game server pids.
 * DONE MOVE THINGS INTO FUNCTIONS
 * DONE load name of pipe from cfg
 * DONE Make an additional thread that loops nonblocking waits on any running servers.
 * DONE Kill and wait for servers if this process is killed.
 */

/*PHP IPC protocol.
 *
 * //STRIKE// A SIGIO will be sent by PHP prior to the transmission of a command.
 * commands will be strings -- null terminated.
 * portions of command will be separated by & characters.
 * (This should avoid conflict with most types of data that would need to be sent,
 * namely names of games, various sorts of numbers, and anything that might need to be
 * sent as a command.)
 * First portion will be a two letter opcode, to be followed by an &
 * Second portion will in most cases be a short game name.
 * It will be possible to \escape an aperstand in any other portion of the string, and
 * to \\ escape a \.
 * further sections of the command may eventually be needed for instances or something?
 *
 * s@css    command should return:
 * s@status
 *
 *
 * f@	    command should return:
 * f@
 * css
 * Counter-Strike: Source
 * cod2
 * Call of Duty 2
 */

/*
 * Main loop:
 * check first server. Loop until all available output has been gotten.
 * check the next server.
 * if all servers don't say anything, wait.
 */

/*
 * Ideas for server/proc communication. Socket? Would allow for communication over prenumbered port.
 * How do we check to see if anything has gotten a hold of us? Do we fork our own listener thread? Can we get
 * an interrupt on data availablility? Hmm...
 */

/*
 * Web console registration. For backend we will need *fmarker* and *lmarker* for each client.
 * Might we run into interesting problems if auth code is all in PHP? I think that will be ok.
 * Backend info on the session should include username and start time, which PHP can send via
 * the command interface.
 */

/*
 * needed tests:
 * onetwo
 * lines of len 99/100
 */


//code not currently in use.
