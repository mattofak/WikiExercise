# Creation script for the WikiExercise
# Matt Walker - 2012

create database if not exists mmwalker_wiki character set = 'utf8';
use mmwalker_wiki;

# yes I know the following is dangerous, but I'm debugging :p
drop table if exists exchange_rates;

create table if not exists exchange_rates(
	currency varchar(3) not null,
	validasof date not null,
	rate float not null,
	
	primary key(currency, validasof),
	index date_lookup using hash (validasof)
);

create user 'wiki_test'@'localhost' identified by 'HighlySecurePassword';
grant select, insert on exchange_rates to 'wiki_test'@'localhost';