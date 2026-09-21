from CarParkingMultiTool import LoginCarParking
import config

client=LoginCarParking(email=config.email, password=config.password)
#Ввывод валюта / Input ID and currency
print('it was')
print(f'Money: {client.data_account.money}\nCoin: {client.data_account.coin}')
print('-----------------------------------------------------')
print('Cheat money')
money=int(input('How many>>'))
#Накрутка денег / Cheating money
client.hack_money(money) # Заменяет(не добавляет) / Replaces (does not add)
client.update() #обновляем информацию с кэкзампляре / updating information from the k instances
print('has become')
print(f'Money: {client.data_account.money}\nCoin: {client.data_account.coin}')
