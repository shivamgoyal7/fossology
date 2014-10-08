<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Data\LicenseDecision;


use Fossology\Lib\Exception;

class LicenseDecisionResult implements LicenseDecision {
  const AGENT_DECISION_TYPE = 'agent';

  /**
   * @var LicenseDecisionEvent
   */
  private $licenseDecisionEvent;

  /**
   * @var array|AgentLicenseDecisionEvent[]
   */
  private $agentDecisionEvents;

  /**
   * @param null|LicenseDecisionEvent $licenseDecisionEvent
   * @param AgentLicenseDecisionEvent[] $agentDecisionEvents
   */
  public function __construct($licenseDecisionEvent, $agentDecisionEvents=array()) {

    $this->licenseDecisionEvent = $licenseDecisionEvent;
    $this->agentDecisionEvents = $agentDecisionEvents;
  }

  /**
   * @throws Exception
   * @return int
   */
  function getLicenseId()
  {
    return $this->getLicenseDecision()->getLicenseId();
  }

  /**
   * @return string
   */
  function getLicenseFullName()
  {
    return $this->getLicenseDecision()->getLicenseFullName();
  }

  /**
   * @return string
   */
  function getLicenseShortName()
  {
    return $this->getLicenseDecision()->getLicenseShortName();
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return $this->getLicenseDecision()->getComment();
  }

  /**
   * @return float
   */
  public function getEpoch()
  {
    return $this->getLicenseDecision()->getEpoch();
  }

  /**
   * @return int
   */
  public function getEventId()
  {
    return $this->getLicenseDecision()->getEventId();
  }

  /**
   * @return string
   */
  public function getEventType()
  {
    return $this->getLicenseDecision()->getEventType();
  }

  /**
   * @return string
   */
  public function getReportinfo()
  {
    return $this->getLicenseDecision()->getReportinfo();
  }

  /**
   * @return boolean
   */
  public function isGlobal()
  {
    return $this->getLicenseDecision()->isGlobal();
  }

  /**
   * @return boolean
   */
  public function isRemoved()
  {
    return $this->getLicenseDecision()->isRemoved();
  }

  /**
   * @throws Exception
   * @return LicenseDecision
   */
  private function getLicenseDecision()
  {
    if (isset($this->licenseDecisionEvent)) {
      return $this->licenseDecisionEvent;
    }
    foreach ($this->agentDecisionEvents as $agentDecisionEvent) {
      return $agentDecisionEvent;
    }
    throw new Exception("could not determine license");
  }

  /**
   * @return bool
   */
  public function hasLicenseDecisionEvent()
  {
    return isset($this->licenseDecisionEvent);
  }

  /**
   * @return array|AgentLicenseDecisionEvent[]
   */
  public function getAgentDecisionEvents()
  {
    return $this->agentDecisionEvents;
  }

  /**
   * @return LicenseDecisionEvent
   */
  public function getLicenseDecisionEvent()
  {
    return $this->licenseDecisionEvent;
  }

}