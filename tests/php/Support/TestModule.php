<?php

class TestPs_Socialfollow extends Ps_Socialfollow
{
    public function exposeUpdateFields()
    {
        return $this->updateFields();
    }

    public function exposeUpgradeConfiguration($name)
    {
        return $this->upgradeConfiguration($name);
    }

    public function exposeGetConfigFieldsValues()
    {
        return $this->getConfigFieldsValues();
    }
}
