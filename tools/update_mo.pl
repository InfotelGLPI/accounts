#!/usr/bin/perl -w
use strict;
use warnings;

# Vérifie qu'aucun argument n'est passé
if (@ARGV != 0) {
    print "USAGE: update_mo.pl\n\n";
    exit();
}

my $dir = "../locales";

opendir(my $dh, $dir) || die "ERROR: cannot read directory $dir\n";
foreach my $file (readdir($dh)) {
    next if $file eq '.' or $file eq '..';

    # On ne traite que les fichiers *.po
    if ($file =~ /\.po$/) {
        my $lang = $file;
        $lang =~ s/\.po$//;

        my $po_file = "$dir/$file";
        my $mo_file = "$dir/$lang.mo";

        print "Compiling $po_file -> $mo_file...\n";

        my $status = system("msgfmt", $po_file, "-o", $mo_file);
        if ($status != 0) {
            warn "ERROR: msgfmt failed on $po_file (exit code $status)\n";
        }
    }
}
closedir $dh;

print "Done.\n";
