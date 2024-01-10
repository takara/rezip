rezip: rezip.php
	box compile


install: ~/.bin/rezip

~/.bin/rezip: rezip
	cp rezip ~/.bin/rezip

clean:
	rm rezip
	
reinstall: clean install
