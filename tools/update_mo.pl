#!/usr/bin/perl -w

#
# -------------------------------------------------------------------------
# accounts plugin for GLPI
# Copyright (C) 2015-2026 by the accounts Development Team.
#
# https://github.com/InfotelGLPI/accounts
# -------------------------------------------------------------------------
#
# LICENSE
#
# This file is part of accounts.
#
# accounts is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# accounts is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with accounts. If not, see <http://www.gnu.org/licenses/>.
# --------------------------------------------------------------------------
#

use strict;
use warnings;

# Vérifie qu'aucun argument n'est passé
if (@ARGV != 0) {
    print "USAGE: update_mo.pl\n\n";
    exit();
}

use Cwd 'abs_path';
use File::Basename;

my $script_dir = dirname(abs_path($0));   # chemin absolu du script
my $dir = "$script_dir/../locales";       # locales un niveau au-dessus du script

-d $dir or die "ERROR: directory $dir does not exist\n";

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
