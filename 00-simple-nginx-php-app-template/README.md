# AMP App Docker

## Levantar contenedores
docker-compose up

## See the results
http://localhost:8092





## Get the name of your MySQL container
docker ps --format '{{.Names}}'

## Connection to the MySQL container
docker exec -ti test-php-mysql-docker-mysql-1 bash

## Connect to MySQL server
mysql -uroot -pmyrootpassword

## We go to the database created when the container is launched
use mysqldb;

## Creation of a "Persons" Table, with a few columns
CREATE TABLE Persons (PersonID int, LastName varchar(255), FirstName varchar(255), Address varchar(255), City varchar(255));

## Insert some data into this table
INSERT INTO Persons VALUES (1, 'John', 'Doe', '51 Birchpond St.', 'New York');
INSERT INTO Persons VALUES (2, 'Jack', 'Smith', '24 Stuck St.', 'Los Angeles');
INSERT INTO Persons VALUES (3, 'Michele', 'Sparrow', '23 Lawyer St.', 'San Diego');





## DB Intro

docker container ls

docker exec -it fb98c92a909f bash

mysql -u root -p myrootpassword

show databases;

create database appsalon;

use appsalon;

show tables;

create table servicios (
	id int(11) not null auto_increment,
    nombre varchar(60) not null,
    precio decimal(8,2) not null,
    primary key (id)
    );

describe servicios;

insert into servicios (nombre, precio) values ("Corte de Cabello de Adulto", 80);
insert into servicios (nombre, precio) values ("Corte de Cabello de Ninyo", 60);
insert into servicios (nombre, precio) values ("Corte de Cabello de Mujer", 70);
insert into servicios (nombre, precio) values
    ("Peinado Hombre", 40),
    ("Peinado Ninyo", 30),
    ("Peinado Mujer", 35);

select * from servicios;
select nombre, precio from servicios;
select * from servicios order by precio desc;
select * from servicios limit 2;
select * from servicios where id = 3;

update servicios set precio = 70 where id = 2;
update servicios set nombre = "Peinado Hombre Actualizado" where id = 4;
update servicios set nombre = "Peinado Mujer Actualizado", precio = 36 where id = 6;

delete from servicios where id = 1;

alter table servicios add descripcion varchar(100) not null;
alter table servicios change descripcion nueva_descripcion varchar(150) not null;
alter table servicios drop column descripcion;

drop table servicios;

create table reservaciones (
    id int(11) not null auto_increment,
    nombre varchar(60) not null,
    apellido varchar(60) not null,
    hora time default null,
    fecha date default null,
    servicios varchar(255) not null,
    primary key (id)
    );

INSERT INTO servicios ( nombre, precio ) VALUES
    ('Corte de Cabello Niño', 60),
    ('Corte de Cabello Hombre', 80),
    ('Corte de Barba', 60),
    ('Peinado Mujer', 80),
    ('Peinado Hombre', 60),
    ('Tinte',300),
    ('Uñas', 400),
    ('Lavado de Cabello', 50),
    ('Tratamiento Capilar', 150);

INSERT INTO reservaciones (nombre, apellido, hora, fecha, servicios) VALUES
    ('Juan', 'De la torre', '10:30:00', '2021-06-28', 'Corte de Cabello Adulto, Corte de Barba' ),
    ('Antonio', 'Hernandez', '14:00:00', '2021-07-30', 'Corte de Cabello Niño'),
    ('Pedro', 'Juarez', '20:00:00', '2021-06-25', 'Corte de Cabello Adulto'),
    ('Mireya', 'Perez', '19:00:00', '2021-06-25', 'Peinado Mujer'),
    ('Jose', 'Castillo', '14:00:00', '2021-07-30', 'Peinado Hombre'),
    ('Maria', 'Diaz', '14:30:00', '2021-06-25', 'Tinte'),
    ('Clara', 'Duran', '10:00:00', '2021-07-01', 'Uñas, Tinte, Corte de Cabello Mujer'),
    ('Miriam', 'Ibañez', '09:00:00', '2021-07-01', 'Tinte'),
    ('Samuel', 'Reyes', '10:00:00', '2021-07-02', 'Tratamiento Capilar'),
    ('Joaquin', 'Muñoz', '19:00:00', '2021-06-28', 'Tratamiento Capilar'),
    ('Julia', 'Lopez', '08:00:00', '2021-06-25', 'Tinte'),
    ('Carmen', 'Ruiz', '20:00:00', '2021-07-01', 'Uñas'),
    ('Isaac', 'Sala', '09:00:00', '2021-07-30', 'Corte de Cabello Adulto'),
    ('Ana', 'Preciado', '14:30:00', '2021-06-28', 'Corte de Cabello Mujer'),
    ('Sergio', 'Iglesias', '10:00:00', '2021-07-02', 'Corte de Cabello Adulto'),
    ('Aina', 'Acosta', '14:00:00', '2021-07-30', 'Uñas'),
    ('Carlos', 'Ortiz', '20:00:00', '2021-06-25', 'Corte de Cabello Niño'),
    ('Roberto', 'Serrano', '10:00:00', '2021-07-30', 'Corte de Cabello Niño'),
    ('Carlota', 'Perez', '14:00:00', '2021-07-01', 'Uñas'),
    ('Ana Maria', 'Igleias', '14:00:00', '2021-07-02', 'Uñas, Tinte'),
    ('Jaime', 'Jimenez', '14:00:00', '2021-07-01', 'Corte de Cabello Niño'),
    ('Roberto', 'Torres', '10:00:00', '2021-07-02', 'Corte de Cabello Adulto'),
    ('Juan', 'Cano', '09:00:00', '2021-07-02', 'Corte de Cabello Niño'),
    ('Santiago', 'Hernandez', '19:00:00', '2021-06-28', 'Corte de Cabello Niño'),
    ('Berta', 'Gomez', '09:00:00', '2021-07-01', 'Uñas'),
    ('Miriam', 'Dominguez', '19:00:00', '2021-06-28', 'Corte de Cabello Niño'),
    ('Antonio', 'Castro', '14:30:00', '2021-07-02', 'Corte de Cabello Adulti'),
    ('Hugo', 'Alonso', '09:00:00', '2021-06-28', 'Corte de Barba'),
    ('Victoria', 'Perez', '10:00:00', '2021-07-02', 'Uñas, Tinte'),
    ('Jimena', 'Leon', '10:30:00', '2021-07-30', 'Uñas, Corte de Cabello Mujer'),
    ('Raquel' ,'Peña', '20:30:00', '2021-06-25', 'Corte de Cabello Mujer');

select * from servicios where precio <= 90;
select * from servicios where precio between 100 and 200;
select count(id), fecha from reservaciones group by fecha order by count(id) desc;
select sum(precio) as totalServicios from servicios;
select min(precio) as precioMenor from servicios;
select max(precio) as precioMayor from servicios;
select * from servicios where nombre like 'Corte%';
select * from servicios where nombre like '%Cabello%';
select * from servicios where nombre like '%Hombre';
select concat(nombre, ' ', apellido) as nombre_completo from reservaciones;
select * from reservaciones where concat(nombre, " ", apellido) like '%Ana Preciado%';
select hora, fecha, concat(nombre, " ", apellido) as 'Nombre Completo' from reservaciones where concat(nombre, " ", apellido) like '%Ana Preciado%';
select * from reservaciones where id in(1,3);
select * from reservaciones where fecha = "2021-06-28" AND id = 1;

drop table reservaciones;

create table clientes (
    id int(11) not null auto_increment,
    nombre varchar(60) not null,
    apellido varchar(60) not null,
    telefono varchar(9) not null,
    email varchar(30) not null unique,
    primary key (id)
    );

insert into clientes (nombre, apellido, telefono, email) values ("Jose", "Rivas", "86354178", "jose@gmail.com");
insert into clientes (nombre, apellido, telefono, email) values ("Juan", "Torres", "12345678", "juan@gmail.com");

create table citas (
    id int(11) not null auto_increment,
    fecha date not null,
    hora time not null,
    cliente_id int(11) not null,
    primary key (id),
    key cliente_id (cliente_id),
    constraint cliente_fk foreign key (cliente_id) references clientes (id)
    );

insert into citas (fecha, hora, cliente_id) values ('2021-06-28', '10:30:00', 1);
insert into citas (fecha, hora, cliente_id) values ('2021-07-28', '12:30:00', 2);

select * from citas a
    inner join clientes b on b.id = a.cliente_id;

create table citasServicios (
    id int(11) not null auto_increment,
    cita_id int(11) not null,
    servicio_id int(11) not null,
    primary key (id),
    key cita_id (cita_id),
    constraint cita_fk foreign key (cita_id) references citas (id),
    key servicio_id (servicio_id),
    constraint servicio_fk foreign key (servicio_id) references servicios (id)
    );

insert into citasServicios (cita_id, servicio_id) values (2, 8);
insert into citasServicios (cita_id, servicio_id) values (2, 3);

select * from citasServicios a
    left join citas b on b.id = a.cita_id
    left join servicios c on c.id = a.servicio_id
    left join clientes d on d.id = b.cliente_id;

