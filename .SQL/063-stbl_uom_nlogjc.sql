# --------------------------------------------------------
# Host:                         127.0.0.1
# Server version:               5.1.28-rc-community
# Server OS:                    Win32
# HeidiSQL version:             6.0.0.3889
# Date/time:                    2012-06-22 11:20:47
# --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

# Dumping structure for table nlogjc.stbl_uom
DROP TABLE IF EXISTS `stbl_uom`;
CREATE TABLE IF NOT EXISTS `stbl_uom` (
  `uomID` varchar(10) NOT NULL,
  `uomType` varchar(255) NOT NULL DEFAULT '',
  `uomTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `uomTitle` varchar(255) NOT NULL DEFAULT '',
  `uomRateToDefault` decimal(12,4) DEFAULT '1.0000',
  `uomOrder` int(11) DEFAULT NULL,
  `uomFlagDefault` tinyint(4) NOT NULL DEFAULT '0',
  `uomFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `uomCode1C` int(11) DEFAULT NULL,
  `uomInsertBy` varchar(50) DEFAULT NULL,
  `uomInsertDate` datetime DEFAULT NULL,
  `uomEditBy` varchar(50) DEFAULT NULL,
  `uomEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`uomType`,`uomID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

# Dumping data for table nlogjc.stbl_uom: ~35 rows (approximately)
/*!40000 ALTER TABLE `stbl_uom` DISABLE KEYS */;
INSERT INTO `stbl_uom` (`uomID`, `uomType`, `uomTitleLocal`, `uomTitle`, `uomRateToDefault`, `uomOrder`, `uomFlagDefault`, `uomFlagDeleted`, `uomCode1C`, `uomInsertBy`, `uomInsertDate`, `uomEditBy`, `uomEditDate`) VALUES
	('dst', '', 'расстояние', 'distance', NULL, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('len', '', 'длина', 'length', NULL, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('loc', '', 'местоположение', 'location', NULL, 9, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('qty', '', 'количество', 'quantity', NULL, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('spd', '', 'скорость', 'speed', NULL, 6, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('squ', '', 'площадь', 'square', NULL, 7, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('tmp', '', 'время', 'time', NULL, 8, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('vol', '', 'объем', 'volume', NULL, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('wgt', '', 'вес', 'weight', NULL, 1, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('km', 'dst', 'км', 'km', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('mi', 'dst', 'миль', 'miles', 1.6093, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('NM', 'dst', 'м.миль', 'NM', 1.8520, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('cm', 'len', 'cм', 'cm', 0.0100, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('ft', 'len', 'футов', 'feet', 0.3048, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('in', 'len', 'дюймов', 'inch', 0.0254, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('m', 'len', 'м', 'm', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('mm', 'len', 'мм', 'mm', 0.0010, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('cnt', 'qty', 'контейнеров', 'containers', 1.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('crt', 'qty', 'коробок', 'cartons', 1.0000, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('pal', 'qty', 'паллет', 'palletes', 1.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('pck', 'qty', 'упаковок', 'packs', 1.0000, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('pcs', 'qty', 'шт', 'pcs', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('kmh', 'spd', 'км/ч', 'km/h', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('knots', 'spd', 'узлов', 'knots', 1.8520, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('mph', 'spd', 'миль/ч', 'mph', 1.6093, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('ms', 'spd', 'м/с', 'm/s', 3.6000, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('hec', 'squ', 'га', 'hectare', 10000.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('km2', 'squ', 'км2', 'km2', 1000000.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('m2', 'squ', 'м2', 'm2', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('day', 'tmp', 'дней', 'days', 86400.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('hrs', 'tmp', 'часов', 'hours', 3600.0000, 1, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('min', 'tmp', 'минут', 'min', 60.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('sec', 'tmp', 'секунд', 'sec', 1.0000, 4, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('l', 'vol', 'л', 'l', 0.0010, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('m3', 'vol', 'м3', 'm3', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('g', 'wgt', 'г', 'g', 0.0010, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('kg', 'wgt', 'кг', 'kg', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
	('lbs', 'wgt', 'фунтов', 'lbs', 0.4536, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
	('t', 'wgt', 'т', 't', 1000.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL);
/*!40000 ALTER TABLE `stbl_uom` ENABLE KEYS */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
