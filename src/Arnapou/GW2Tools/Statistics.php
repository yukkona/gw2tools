<?php

/*
 * This file is part of the Arnapou GW2Tools package.
 *
 * (c) Arnaud Buathier <arnaud@arnapou.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Arnapou\GW2Tools;

use Arnapou\GW2Api\Model\Character;
use Arnapou\GW2Api\Model\Item;

class Statistics {

    /**
     *
     * @var Statistics 
     */
    static protected $instance;

    /**
     *
     * @var string
     */
    protected $lang;

    /**
     *
     * @var \MongoCollection
     */
    protected $collection;

    /**
     *
     * @var Gw2Account
     */
    protected $account;

    /**
     * 
     * @return Statistics
     */
    static public function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 
     */
    public function __construct() {
        $this->lang       = Translator::getInstance()->getLang();
        $this->collection = MongoCache::getInstance(false)->getMongoDB()->selectCollection('statistics');
        $this->collection->ensureIndex('account');
    }

    /**
     * 
     * @return int
     */
    public function getCount() {
        return $this->cacheGet('statistics/count', function() {
                return $this->collection->count();
            });
    }

    /**
     * 
     * @return \MongoCollection
     */
    public function getCollection() {
        return $this->collection;
    }

    /**
     * 
     * @return Gw2Account
     */
    public function setAccount(Gw2Account $account) {
        $this->account = $account;
    }

    /**
     * 
     * @return Gw2Account
     */
    public function getAccount() {
        return $this->account;
    }

    /**
     * 
     * @param string $key
     * @return string
     */
    protected function trans($key) {
        return Translator::getInstance()->trans($key);
    }

    /**
     * 
     * @param string $key
     * @param callable $callable
     * @param integer $expiration
     * @return array
     */
    protected function cacheGet($key, $callable, $expiration = 900) {
        $cacheKey = 'statistics/' . $this->lang . '/' . $key;
        $cache    = MongoCache::getInstance();
        $result   = $cache->get($cacheKey);
        if ($result) {
            return $result;
        }
        $result = $callable();
        $cache->set($cacheKey, $result, $expiration);
        return $result;
    }

    /**
     * 
     * @param string $key
     * @param string $subkey
     * @param mixed $uservalue
     * @return array
     */
    protected function calcPercentiles($key, $subkey, $uservalue = null) {
        $cacheKey = 'percentile/' . $key . '/' . $subkey . '/' . ($this->account ? $this->account->getName() : '');
        return $this->cacheGet($cacheKey, function() use ($key, $subkey, $uservalue) {
                $userindex = null;
                $array     = $this->cacheGet('rawpercentile/' . $key . '/' . $subkey, function() use ($key, $subkey) {
                    foreach ($this->collection->find() as $row) {
                        if (isset($row[$key], $row[$key][$subkey])) {
                            $array[] = $row[$key][$subkey];
                        }
                    }
                    rsort($array);
                    return $array;
                }, 4 * 3600);
                $n        = ceil(count($array) / 100);
                $chunks   = array_chunk($array, $n);
                $nbchunks = count($chunks);
                $data     = array_fill(0, 100, null);
                foreach ($chunks as $i => $chunk) {
                    $value = $chunk[0];
                    $index = floor(($i + 1) * 100 / $nbchunks) - 1;
                    if ($uservalue !== null && $uservalue <= $value) {
                        $userindex = $index;
                    }
                    $data[$index] = $value;
                }
                $result = [$data];
                if ($userindex !== null) {
                    $userdata             = array_fill(0, 100, null);
                    $userdata[$userindex] = $uservalue;
                    $result[]             = $userdata;
                }
                return $result;
            });
    }

    /**
     * 
     * @return array
     */
    public function getDatasetItemsLegendary() {
        $value = $this->account ? $this->account->getLegendariesCount()['Total'] : null;
        return $this->calcPercentiles(Item::RARITY_LEGENDARY, 'Total', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetItemsAscended() {
        $value = $this->account ? $this->account->getAscendedCount()['Total'] : null;
        return $this->calcPercentiles(Item::RARITY_ASCENDED, 'Total', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetItemsAscendedWeapons() {
        $value = $this->account ? $this->account->getAscendedCount()[Item::TYPE_WEAPON] : null;
        return $this->calcPercentiles(Item::RARITY_ASCENDED, Item::TYPE_WEAPON, $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetItemsAscendedArmors() {
        $value = $this->account ? $this->account->getAscendedCount()[Item::TYPE_ARMOR] : null;
        return $this->calcPercentiles(Item::RARITY_ASCENDED, Item::TYPE_ARMOR, $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetItemsAscendedBacks() {
        $value = $this->account ? $this->account->getAscendedCount()[Item::TYPE_BACK] : null;
        return $this->calcPercentiles(Item::RARITY_ASCENDED, Item::TYPE_BACK, $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetItemsAscendedTrinkets() {
        $value = $this->account ? $this->account->getAscendedCount()[Item::TYPE_TRINKET] : null;
        return $this->calcPercentiles(Item::RARITY_ASCENDED, Item::TYPE_TRINKET, $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetPvpWinrates() {
        $value = $this->account ? $this->account->getStatsPvp()['winrate'] : null;
        return $this->calcPercentiles('pvp', 'winrate', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetPvpWins() {
        $value = $this->account ? $this->account->getStatsPvp()['wins'] : null;
        return $this->calcPercentiles('pvp', 'wins', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetPvpTotals() {
        $value = $this->account ? $this->account->getStatsPvp()['total'] : null;
        return $this->calcPercentiles('pvp', 'total', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetPvpRanks() {
        $value = $this->account ? $this->account->getStatsPvp()['rank'] : null;
        return $this->calcPercentiles('pvp', 'rank', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetCharacters() {
        $value = $this->account ? $this->account->getCharactersCount() : null;
        return $this->calcPercentiles('generic', 'characters', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetDeaths() {
        $value = $this->account ? $this->account->getTotalDeaths() : null;
        return $this->calcPercentiles('generic', 'deaths', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetAge() {
        $value = $this->account ? $this->account->getTotalAge() : null;
        return $this->calcPercentiles('generic', 'age', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetLevel80() {
        $value = $this->account ? $this->account->getCharactersLevel80Count() : null;
        return $this->calcPercentiles('generic', 'level80', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetDyes() {
        $value = $this->account ? $this->account->getStatsUnlocks()['dyes'] : null;
        return $this->calcPercentiles('unlocks', 'dyes', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetSkins() {
        $value = $this->account ? $this->account->getStatsUnlocks()['skins'] : null;
        return $this->calcPercentiles('unlocks', 'skins', $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetGolds() {
        $value = $this->account ? $this->account->getStatsWallet()[1] : null;
        return $this->calcPercentiles('wallet', 1, $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetKarma() {
        $value = $this->account ? $this->account->getStatsWallet()[2] : null;
        return $this->calcPercentiles('wallet', 2, $value);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetProfessions() {
        return $this->cacheGet('pie/professions/', function() {
                $data = [
                    Character::PROFESSION_ELEMENTALIST => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_ELEMENTALIST)],
                    Character::PROFESSION_ENGINEER     => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_ENGINEER)],
                    Character::PROFESSION_GUARDIAN     => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_GUARDIAN)],
                    Character::PROFESSION_MESMER       => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_MESMER)],
                    Character::PROFESSION_NECROMANCER  => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_NECROMANCER)],
                    Character::PROFESSION_RANGER       => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_RANGER)],
                    Character::PROFESSION_REVENANT     => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_REVENANT)],
                    Character::PROFESSION_THIEF        => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_THIEF)],
                    Character::PROFESSION_WARRIOR      => [ 'y' => 0, 'name' => $this->trans('profession.' . Character::PROFESSION_WARRIOR)],
                ];
                foreach ($this->collection->find() as $row) {
                    if (isset($row['profession'])) {
                        foreach ($row['profession'] as $key => $value) {
                            $data[$key]['y'] += $value;
                        }
                    }
                }
                return array_values($data);
            }, 4 * 3600);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetGenders() {
        return $this->cacheGet('pie/genders/', function() {
                $data = [
                    Character::GENDER_MALE   => [ 'y' => 0, 'name' => $this->trans('gender.' . Character::GENDER_MALE)],
                    Character::GENDER_FEMALE => [ 'y' => 0, 'name' => $this->trans('gender.' . Character::GENDER_FEMALE)],
                ];
                foreach ($this->collection->find() as $row) {
                    if (isset($row['gender'])) {
                        foreach ($row['gender'] as $key => $value) {
                            $data[$key]['y'] += $value;
                        }
                    }
                }
                return array_values($data);
            }, 4 * 3600);
    }

    /**
     * 
     * @return array
     */
    public function getDatasetRaces() {
        return $this->cacheGet('pie/races/', function() {
                $data = [
                    Character::RACE_ASURA   => [ 'y' => 0, 'name' => $this->trans('race.' . Character::RACE_ASURA)],
                    Character::RACE_CHARR   => [ 'y' => 0, 'name' => $this->trans('race.' . Character::RACE_CHARR)],
                    Character::RACE_HUMAN   => [ 'y' => 0, 'name' => $this->trans('race.' . Character::RACE_HUMAN)],
                    Character::RACE_NORN    => [ 'y' => 0, 'name' => $this->trans('race.' . Character::RACE_NORN)],
                    Character::RACE_SYLVARI => [ 'y' => 0, 'name' => $this->trans('race.' . Character::RACE_SYLVARI)],
                ];
                foreach ($this->collection->find() as $row) {
                    if (isset($row['race'])) {
                        foreach ($row['race'] as $key => $value) {
                            $data[$key]['y'] += $value;
                        }
                    }
                }
                return array_values($data);
            }, 4 * 3600);
    }

}
