# $Id$
#
# Makefile to generate html doc from reStructuredText source
#
TARGETDIR=target

all:prepare
	rst2html.py README.rst $(TARGETDIR)/README.html
	rst2html.py overview.rst $(TARGETDIR)/overview.html
	rst2html.py tuning.rst $(TARGETDIR)/tuning.html
	rst2html.py java/nio.rst $(TARGETDIR)/nio.html
	rst2html.py server/HMaster.rst $(TARGETDIR)/HMaster.html
	rst2html.py server/HRegionServer.rst $(TARGETDIR)/HRegionServer.html
	rst2html.py learned/naming.rst $(TARGETDIR)/naming.html
	rst2html.py learned/typo.rst $(TARGETDIR)/typo.html
	@echo 
	@echo "done!!!"

prepare:
	mkdir -p $(TARGETDIR)

clean:
	rm -rf $(TARGETDIR)
	
