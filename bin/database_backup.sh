#!/bin/bash
HOST=${HOST:-backup.host}
USER=${USER:-user}
PASSWORD=${PASSWORD:-pass}
KEEPBACKUPDAYS=${KEEPBACKUPDAYS:-10}
KEEPMONTHLYBACKUPDAYS=${KEEPMONTHLYBACKUPDAYS:-400}
DIRECTORYMONGO=${DIRECTORYMONGO:-/var/backups/mongobackups}
DIRECTORYFILES=${DIRECTORYFILES:-/var/backups/gogocarto-uploads}
GOGOCARTODIRECTORY=${GOGOCARTODIRECTORY:-/var/www/gogocarto}
MONTHLYFOLDER=${MONTHLYFOLDER:-monthly}
LOG=$DIRECTORYMONGO/backup.log
TODAY=`date +"%Y-%m-%d"`
# See https://www.linuxbabe.com/mail-server/set-up-postfix-smtp-relay-debian-sendinblue how to configure email
NOTIF_EMAILS='sebastian.castro@protonmail.com,v.farcy72@gmail.com'

if [ ! -d "$DIRECTORYMONGO" ]; then
  mkdir -p $DIRECTORYMONGO
fi

if [ ! -d "$DIRECTORYMONGO/$MONTHLYFOLDER" ]; then
  mkdir -p $DIRECTORYMONGO/$MONTHLYFOLDER
fi

if [ ! -d "$DIRECTORYFILES" ]; then
  mkdir -p $DIRECTORYFILES
fi

printf "\n\n" >> $LOG

# backup mongodb
echo $(date) "Starting mongo dump" >> $LOG
mongodump --out $DIRECTORYMONGO/$TODAY --gzip

# monthly backup
if [ "$(date '+%d')" -eq 01 ]; then
  cp -r $DIRECTORYMONGO/$TODAY $DIRECTORYMONGO/$MONTHLYFOLDER
fi

# backup gogocarto files
echo $(date) "Starting backup upload" >> $LOG
mkdir -p $DIRECTORYFILES/$TODAY
tar -zcvf $DIRECTORYFILES/$TODAY/uploads.tar.gz $GOGOCARTODIRECTORY/web/uploads

# clean older backups
echo $(date) "Clean old backups" >> $LOG
find $DIRECTORYFILES/ -mtime +$KEEPBACKUPDAYS -exec rm -rf {} \;
find $DIRECTORYMONGO/20* -mtime +$KEEPBACKUPDAYS -exec rm -rf {} \;
find $DIRECTORYMONGO/$MONTHLYFOLDER -mtime +$KEEPMONTHLYBACKUPDAYS -exec rm -rf {} \;

# compress and remplace existing on ftp
echo $(date) "Compress" >> $LOG
tar -zcvf $DIRECTORYFILES/$TODAY/mongo.tar.gz $DIRECTORYMONGO/$TODAY

cd $DIRECTORYFILES/$TODAY
echo $(date) "Send to FTP" >> $LOG
lftp -u $USER,$PASSWORD $HOST -e "mkdir -p gogocarto/$TODAY; exit"
lftp -u $USER,$PASSWORD $HOST -e "cd gogocarto/$TODAY;put mongo.tar.gz; exit"
lftp -u $USER,$PASSWORD $HOST -e "cd gogocarto/$TODAY;put uploads.tar.gz; exit"
# Keep 15 days mongo backup
lftp -u $USER,$PASSWORD $HOST -e "rm -rf gogocarto/`date --date='15 day ago' +%Y-%m-%d`; exit"
# Keep 3 days uploads backups
lftp -u $USER,$PASSWORD $HOST -e "rm -f gogocarto/`date --date='3 day ago' +%Y-%m-%d`/uploads.tar.gz; exit"

# Remove local tarballs 
# we keep the unzip mongo backup
# for upload uploads backup, it take too much space and have never used it. If needed we will use the one uploaded on FTP
rm -rf $DIRECTORYFILES/`date +"%Y-%m-%d"`

# Warning message
remaining_size=$(echo "du" | lftp -u $USER,$PASSWORD $HOST | awk -v LIMIT=100 '$2=="." {printf "%.0f", ((LIMIT*1024*1024)-$1)/1024/1024}')
echo $(date) "Remaining space on FTP: $remaining_size Go" >> $LOG
if [ "$remaining_size" -lt 20 ]; then
  echo "Only $remaining_size G left on FTP server" | mailx -r noreply@gogocarto.fr -s "GoGoCarto Backup Warning" $NOTIF_EMAILS
fi

uploaded_path=$(lftp -u $USER,$PASSWORD $HOST -e "find gogocarto/$TODAY/mongo.tar.gz; exit;")
if [[ $uploaded_path != "gogocarto/$TODAY/mongo.tar.gz" ]]; then
  echo $(date) "ERROR: last uploaded file does not exists on FTP server" >> $LOG
  echo "A problem happened during backup, last file does not exists" | mailx -r noreply@gogocarto.fr -s "GoGoCarto Backup Error" $NOTIF_EMAILS
fi
